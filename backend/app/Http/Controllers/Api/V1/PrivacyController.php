<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit;
use App\Support\SyncRegistry;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

/** Tenant data portability and the erasure request (owner only). */
class PrivacyController extends Controller
{
    /** Full export as newline-delimited JSON, streamed so large companies do not exhaust memory. */
    public function export()
    {
        $tenant = app(Tenancy::class)->get();
        Audit::log('tenant.data_exported', 'tenant', $tenant->id);
        $name = "export-{$tenant->slug}-".now()->format('Ymd-His').'.ndjson';

        return response()->streamDownload(function () use ($tenant) {
            $write = function (string $entity, array $row) {
                echo json_encode(['entity' => $entity, 'row' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };
            $write('tenant', $tenant->only(['id', 'name', 'slug', 'status', 'currency', 'default_locale', 'timezone', 'created_at']));
            User::where('tenant_id', $tenant->id)->get(['id', 'name', 'email', 'phone', 'locale', 'is_active', 'created_at'])->each(fn ($u) => $write('users', $u->toArray()));
            Role::get()->each(fn ($r) => $write('roles', $r->toArray()));
            Setting::get()->each(fn ($s) => $write('settings', $s->toArray()));
            foreach (SyncRegistry::entities() as $entity => [$model]) {
                $q = $model::query();
                if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                    $q->withTrashed();
                }
                $q->chunkById(500, fn ($rows) => $rows->each(fn ($r) => $write($entity, $r->toArray())));
            }
            Invoice::where('tenant_id', $tenant->id)->get()->each(fn ($i) => $write('invoices', $i->toArray()));
        }, $name, ['Content-Type' => 'application/x-ndjson']);
    }

    public function requestDeletion(Request $r)
    {
        $d = $r->validate(['confirm' => 'required|in:DELETE', 'reason' => 'nullable|string|max:300']);
        $tenant = app(Tenancy::class)->get();
        $tenant->forceFill(['meta' => array_merge($tenant->meta ?? [], ['deletion_requested_at' => now()->toIso8601String(), 'deletion_reason' => $d['reason'] ?? null])])->save();
        Audit::log('tenant.deletion_requested', 'tenant', $tenant->id, null, ['reason' => $d['reason'] ?? null]);

        return response()->json(['message' => 'Deletion requested. We will confirm with you before erasing anything. Export your data first.']);
    }
}
