<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit;
use App\Services\Billing\BillingService;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Super-admin console API (platform owner only, 2FA required). */
class PlatformController extends Controller
{
    public function metrics()
    {
        $byStatus = Tenant::select('status', DB::raw('COUNT(*) n'))->groupBy('status')->pluck('n', 'status');
        $mrr = (int) Tenant::where('tenants.status', 'active')->join('plans', 'plans.id', '=', 'tenants.plan_id')->sum('plans.price_monthly');

        return response()->json(['data' => [
            'tenants' => $byStatus, 'tenants_total' => (int) $byStatus->sum(), 'users' => User::whereNotNull('tenant_id')->count(), 'devices_active_7d' => Device::withoutGlobalScopes()->where('last_seen_at', '>', now()->subDays(7))->count(),
            'mrr' => $mrr, 'revenue_collected' => (int) Invoice::where('status', 'paid')->sum('amount'), 'invoices_pending' => Invoice::where('status', 'pending')->count(),
            'signups_30d' => Tenant::where('created_at', '>', now()->subDays(30))->count(),
        ]]);
    }

    public function tenants(Request $r)
    {
        $q = Tenant::withTrashed()->with('plan:id,key,name')->orderByDesc('created_at');
        if ($r->filled('status')) {
            $q->where('status', $r->query('status'));
        }
        if ($r->filled('q')) {
            $t = '%'.addcslashes($r->query('q'), '%_\\').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('slug', 'like', $t));
        }
        $p = $q->paginate(min((int) $r->query('per_page', 50), 200));

        return response()->json(['data' => $p->items(), 'meta' => ['page' => $p->currentPage(), 'total' => $p->total()]]);
    }

    public function tenant(string $id)
    {
        $t = Tenant::withTrashed()->with('plan')->findOrFail($id);

        return response()->json(['data' => $t->toArray() + [
            'modules' => $t->enabledModules(), 'users' => User::where('tenant_id', $t->id)->count(),
            'owner' => User::where('tenant_id', $t->id)->whereHas('roles', fn ($q) => $q->where('key', 'owner'))->first(['id', 'name', 'email', 'phone']),
            'invoices' => Invoice::where('tenant_id', $t->id)->orderByDesc('created_at')->limit(20)->get(),
        ]]);
    }

    public function setStatus(Request $r, string $id)
    {
        $d = $r->validate(['status' => 'required|in:trial,active,grace,suspended', 'reason' => 'nullable|string|max:200', 'paid_until' => 'nullable|date']);
        $t = Tenant::findOrFail($id);
        $before = $t->status;
        $t->forceFill(['status' => $d['status']] + (isset($d['paid_until']) ? ['paid_until' => $d['paid_until']] : []))->save();
        Audit::log('platform.tenant_status', 'tenant', $t->id, ['status' => $before], ['status' => $d['status'], 'reason' => $d['reason'] ?? null], $t->id);

        return response()->json(['data' => $t]);
    }

    public function setPlan(Request $r, string $id, BillingService $billing)
    {
        $d = $r->validate(['plan_id' => 'required|uuid|exists:plans,id']);
        $t = Tenant::findOrFail($id);
        $t->forceFill(['plan_id' => $d['plan_id']])->save();
        $billing->syncModulesToPlan($t->fresh());
        Audit::log('platform.tenant_plan', 'tenant', $t->id, null, $d, $t->id);

        return response()->json(['data' => $t->fresh('plan')]);
    }

    public function markInvoicePaid(Request $r, string $id, BillingService $billing)
    {
        $d = $r->validate(['reference' => 'required|string|max:100']);
        $inv = $billing->markPaid(Invoice::findOrFail($id), $d['reference']);

        return response()->json(['data' => $inv]);
    }

    /** Short-lived token as the tenant owner for support. Always audited, visible in the tenant's audit log. */
    public function impersonate(Request $r, string $id)
    {
        $d = $r->validate(['reason' => 'required|string|max:200']);
        $t = Tenant::findOrFail($id);
        $owner = User::where('tenant_id', $t->id)->whereHas('roles', fn ($q) => $q->where('key', 'owner'))->firstOrFail();
        Audit::log('platform.impersonation', 'user', $owner->id, null, ['by' => $r->user()->id, 'reason' => $d['reason']], $t->id);
        $token = $owner->createToken('impersonation', ['*'], now()->addMinutes(30));

        return response()->json(['access_token' => $token->plainTextToken, 'expires_in' => 1800, 'tenant' => $t->only(['id', 'name', 'slug'])]);
    }

    public function plans()
    {
        return response()->json(['data' => Plan::orderBy('price_monthly')->get(), 'modules' => Modules::switchable()]);
    }

    public function savePlan(Request $r, ?string $id = null)
    {
        $d = $r->validate(['key' => ($id ? 'sometimes' : 'required').'|alpha_dash|max:40', 'name' => ($id ? 'sometimes' : 'required').'|string|max:80', 'price_monthly' => 'integer|min:0',
            'modules' => 'array', 'modules.*' => 'in:'.implode(',', Modules::switchable()), 'limits' => 'nullable|array', 'is_active' => 'boolean']);
        $plan = $id ? Plan::findOrFail($id) : new Plan;
        $plan->fill($d)->save();
        Audit::log('platform.plan_saved', 'plan', $plan->id, null, $d);

        return response()->json(['data' => $plan], $id ? 200 : 201);
    }

    public function audit(Request $r)
    {
        $q = AuditLog::orderByDesc('created_at');
        foreach (['tenant_id', 'action', 'user_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }

        return response()->json($q->paginate(100));
    }
}
