<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\Billing\BillingService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** Tenant-facing subscription billing (owner only). Works even when the workspace is suspended. */
class BillingController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function show()
    {
        $t = app(Tenancy::class)->get()->load('plan');

        return response()->json(['data' => [
            'status' => $t->status, 'plan' => $t->plan, 'trial_ends_at' => $t->trial_ends_at, 'paid_until' => $t->paid_until, 'grace_ends_at' => $t->grace_ends_at,
            'plans' => Plan::where('is_active', true)->where('key', '!=', 'trial')->orderBy('price_monthly')->get(),
            'invoices' => Invoice::where('tenant_id', $t->id)->orderByDesc('created_at')->limit(50)->get(),
        ]]);
    }

    public function choosePlan(Request $r)
    {
        $d = $r->validate(['plan_id' => 'required|uuid']);
        $plan = Plan::where('is_active', true)->findOrFail($d['plan_id']);
        $inv = $this->billing->invoiceFor(app(Tenancy::class)->get(), $plan);

        return response()->json(['data' => $inv], 201);
    }

    public function pay(Request $r, string $id)
    {
        $d = $r->validate(['phone' => 'required|string|max:20']);
        $inv = Invoice::where('tenant_id', app(Tenancy::class)->id())->findOrFail($id);

        return response()->json(['data' => $this->billing->startPayment($inv, $d['phone'])]);
    }

    public function invoice(string $id)
    {
        $inv = Invoice::where('tenant_id', app(Tenancy::class)->id())->findOrFail($id);

        return response()->json(['data' => $this->billing->refresh($inv)]);
    }
}
