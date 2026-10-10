<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SyncReceipt;
use App\Support\SyncRegistry;
use App\Support\Tenancy;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

/**
 * Offline sync for the Android app.
 *  pull: per-entity cursors "updated_at|id" -> changed rows (soft-deleted rows are tombstones)
 *  push: ordered outbox items replayed through the normal API, so every business rule still applies
 */
class SyncController extends Controller
{
    private function allowed(Request $r): array
    {
        $enabled = app(Tenancy::class)->get()->enabledModules();
        $out = [];
        foreach (SyncRegistry::entities() as $name => [$model, $module, $perms]) {
            if ($module && ! in_array($module, $enabled, true)) {
                continue;
            }
            if ($perms) {
                $ok = false;
                foreach ($perms as $p) {
                    $ok = $ok || $r->user()->can2($p);
                }
                if (! $ok) {
                    continue;
                }
            }
            $out[$name] = $model;
        }

        return $out;
    }

    public function pull(Request $r)
    {
        $d = $r->validate(['entities' => 'nullable|string', 'cursors' => 'nullable|string', 'limit' => 'nullable|integer|min:1|max:1000', 'property_id' => 'nullable|uuid']);
        $allowed = $this->allowed($r);
        $want = isset($d['entities']) ? array_values(array_intersect(explode(',', $d['entities']), array_keys($allowed))) : array_keys($allowed);
        $cursors = isset($d['cursors']) ? (json_decode(base64_decode($d['cursors']), true) ?: []) : [];
        $limit = (int) ($d['limit'] ?? 300);
        $changes = [];
        $next = $cursors;
        $more = false;
        foreach ($want as $name) {
            $model = $allowed[$name];
            $q = $model::query()->orderBy('updated_at')->orderBy('id');
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $q->withTrashed();
            }
            if (! empty($cursors[$name])) {
                [$ts, $id] = array_pad(explode('|', $cursors[$name], 2), 2, '');
                $q->where(fn ($w) => $w->where('updated_at', '>', $ts)->orWhere(fn ($x) => $x->where('updated_at', $ts)->where('id', '>', $id)));
            }
            $rows = $q->limit($limit + 1)->get();
            if ($rows->count() > $limit) {
                $more = true;
                $rows = $rows->take($limit);
            }
            $changes[$name] = $rows->values();
            if ($rows->isNotEmpty()) {
                $last = $rows->last();
                $next[$name] = $last->updated_at->format('Y-m-d H:i:s').'|'.$last->id;
            }
        }

        return response()->json(['changes' => $changes, 'cursors' => base64_encode(json_encode($next)), 'has_more' => $more, 'server_time' => now()->toIso8601String()]);
    }

    /** Push-able actions: [entity => [op => route template]] with {id} taken from the payload. */
    private function route(array $item): ?array
    {
        $p = $item['payload'] ?? [];
        $id = $p['id'] ?? $item['id'] ?? null;
        $action = $p['action'] ?? null;
        $e = $item['entity'];
        $map = [
            'order' => ['create' => ['POST', '/orders'], 'update' => ['POST', "/orders/$id/".($action ?? '')], 'void' => ['POST', "/orders/$id/void"]],
            'order_item' => ['update' => ['POST', "/order-items/$id/".($action ?? '')], 'void' => ['POST', "/order-items/$id/void"]],
            'reservation' => ['create' => ['POST', '/reservations'], 'update' => ['POST', "/reservations/$id/".($action ?? '')]],
            'booking' => ['create' => ['POST', '/bookings'], 'update' => ['PATCH', "/bookings/$id"]],
            'booking_payment' => ['create' => ['POST', '/bookings/'.($p['booking_id'] ?? '').'/payments']],
            'folio_payment' => ['create' => ['POST', '/folios/'.($p['folio_id'] ?? '').'/payments']],
            'attendance' => ['create' => ['POST', '/attendance']],
            'task' => ['update' => ['POST', "/tasks/$id/".($action ?? '')]],
            'shift' => ['create' => ['POST', '/shifts'], 'update' => ['POST', "/shifts/$id/close"]],
            'stock_adjustment' => ['create' => ['POST', '/stock/adjust']],
            'guest' => ['create' => ['POST', '/guests'], 'update' => ['PATCH', "/guests/$id"]],
            'expense' => ['create' => ['POST', '/expenses']],
        ];
        $hit = $map[$e][$item['op']] ?? null;
        if ($hit && str_ends_with($hit[1], '/')) {
            return null; // update without an action
        }

        return $hit;
    }

    public function push(Request $r)
    {
        $d = $r->validate(['items' => 'required|array|min:1|max:100', 'items.*.id' => 'required|string|max:80', 'items.*.entity' => 'required|string', 'items.*.op' => 'required|in:create,update,void',
            'items.*.idempotency_key' => 'required|string|max:120', 'items.*.payload' => 'nullable|array', 'device_id' => 'nullable|uuid']);
        $tenantId = app(Tenancy::class)->id();
        $kernel = app(Kernel::class);
        $results = [];
        foreach ($d['items'] as $item) {
            $prior = SyncReceipt::where('tenant_id', $tenantId)->where('idempotency_key', $item['idempotency_key'])->first();
            if ($prior) {
                $results[] = ['id' => $item['id'], 'status' => 'duplicate', 'http_status' => $prior->status_code, 'data' => $prior->response['data'] ?? null];

                continue;
            }
            $route = $this->route($item);
            if (! $route) {
                $results[] = ['id' => $item['id'], 'status' => 'rejected', 'reason' => 'unsupported_operation'];

                continue;
            }
            [$method, $path] = $route;
            $payload = $item['payload'] ?? [];
            unset($payload['action']);
            if ($item['op'] === 'create' && $item['entity'] !== 'shift' && ! isset($payload['id']) && in_array($item['entity'], ['order', 'reservation', 'booking', 'attendance', 'guest', 'expense'], true)) {
                $payload['id'] = $item['id'];
            }
            $payload['idempotency_key'] = $payload['idempotency_key'] ?? $item['idempotency_key'];
            $sub = Request::create('/api/v1'.$path, $method, $payload, [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => $r->header('Authorization'), 'REMOTE_ADDR' => $r->ip()]);
            $outer = app('request');
            try {
                $res = $kernel->handle($sub);
            } finally {
                app()->instance('request', $outer);
                app(Tenancy::class)->set(app(Tenancy::class)->get() ?? null);
            }
            $code = $res->getStatusCode();
            $body = json_decode($res->getContent(), true);
            $status = $code < 300 ? 'applied' : (in_array($code, [409]) ? 'conflict' : 'rejected');
            $out = ['id' => $item['id'], 'status' => $status, 'http_status' => $code] + ($code < 300 ? ['data' => $body['data'] ?? $body] : ['reason' => $body['error']['code'] ?? 'error', 'message' => $body['error']['message'] ?? null, 'details' => $body['error']['details'] ?? null]);
            // Remember final outcomes. Transient server errors are not stored so the client can retry.
            if ($code < 500) {
                SyncReceipt::create(['tenant_id' => $tenantId, 'idempotency_key' => $item['idempotency_key'], 'device_id' => $d['device_id'] ?? null, 'status_code' => $code, 'response' => ['data' => $out['data'] ?? null, 'reason' => $out['reason'] ?? null]]);
            }
            $results[] = $out;
        }

        return response()->json(['results' => $results]);
    }
}
