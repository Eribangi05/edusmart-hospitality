<?php

namespace App\Services\Billing;

use App\Exceptions\ApiError;
use App\Models\Domain\Payment;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\Audit;
use App\Services\Payments\MomoGateway;
use Illuminate\Support\Str;

/**
 * Subscription lifecycle:  trial -> (invoice) -> active -> grace (after paid_until) -> suspended.
 * Suspended tenants keep read-only access; paying an invoice restores them immediately.
 */
class BillingService
{
    public const GRACE_DAYS = 7;

    public function __construct(private MomoGateway $momo) {}

    public function invoiceFor(Tenant $t, ?Plan $plan = null): Invoice
    {
        $plan ??= $t->plan;
        if (! $plan || $plan->price_monthly <= 0) {
            throw new ApiError('invalid_state', 'No billable plan.', 409);
        }
        $start = ($t->paid_until && $t->paid_until->isFuture() ? $t->paid_until : now())->startOfDay();
        $existing = Invoice::where('tenant_id', $t->id)->where('plan_id', $plan->id)->where('status', 'pending')->where('period_start', $start->toDateString())->first();
        if ($existing) {
            return $existing;
        }
        $n = Invoice::where('tenant_id', $t->id)->count() + 1;

        return Invoice::create([
            'tenant_id' => $t->id, 'number' => 'INV-'.strtoupper(substr($t->slug, 0, 6)).'-'.now()->format('Ym').'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(3)),
            'plan_id' => $plan->id, 'lines' => [['description' => $plan->name.' (1 month)', 'amount' => $plan->price_monthly]], 'amount' => $plan->price_monthly,
            'period_start' => $start->toDateString(), 'period_end' => $start->copy()->addMonth()->subDay()->toDateString(), 'due_on' => $start->toDateString(),
        ]);
    }

    /** Ask the payer to approve a mobile-money request for the invoice. */
    public function startPayment(Invoice $inv, string $phone): Invoice
    {
        if ($inv->status !== 'pending') {
            throw new ApiError('invalid_state', 'Invoice is not payable.', 409);
        }
        $fake = new Payment;
        $fake->forceFill(['id' => $inv->id, 'amount' => $inv->amount, 'payer_phone' => $phone]);
        $res = $this->momo->requestToPay($fake);
        $inv->forceFill(['payment_reference' => $res['reference'], 'payer_phone' => $phone, 'payment_status' => 'pending'])->save();

        return $inv;
    }

    public function refresh(Invoice $inv): Invoice
    {
        if ($inv->status === 'pending' && $inv->payment_reference) {
            $status = $this->momo->status($inv->payment_reference);
            if ($status === 'successful') {
                $this->markPaid($inv);
            } elseif ($status === 'failed') {
                $inv->forceFill(['payment_status' => 'failed'])->save();
            }
        }

        return $inv->fresh();
    }

    public function markPaid(Invoice $inv, ?string $reference = null): Invoice
    {
        if ($inv->status === 'paid') {
            return $inv;
        }
        $inv->forceFill(['status' => 'paid', 'payment_status' => 'successful', 'paid_at' => now(), 'payment_reference' => $reference ?? $inv->payment_reference])->save();
        $t = Tenant::findOrFail($inv->tenant_id);
        $base = $t->paid_until && $t->paid_until->isFuture() ? $t->paid_until : now();
        $t->forceFill(['status' => 'active', 'plan_id' => $inv->plan_id, 'paid_until' => $base->copy()->addMonth(), 'grace_ends_at' => null])->save();
        $this->syncModulesToPlan($t);
        Audit::log('billing.invoice_paid', 'invoice', $inv->id, null, ['amount' => $inv->amount], $t->id, null);

        return $inv;
    }

    /** Disable modules the plan does not include (never deletes data). */
    public function syncModulesToPlan(Tenant $t): void
    {
        $allowed = $t->plan?->modules;
        if ($allowed === null) {
            return;
        }
        TenantModule::withoutGlobalScopes()->where('tenant_id', $t->id)->whereNotIn('module_key', $allowed)->update(['enabled' => false]);
    }

    /** Daily job. Returns counts for logging/tests. */
    public function run(): array
    {
        $out = ['invoiced' => 0, 'grace' => 0, 'suspended' => 0];
        foreach (Tenant::whereIn('status', ['trial', 'active', 'grace'])->get() as $t) {
            if ($t->status === 'grace') {
                if ($t->grace_ends_at && $t->grace_ends_at->isPast()) {
                    $t->forceFill(['status' => 'suspended'])->save();
                    $out['suspended']++;
                    Audit::log('billing.suspended', 'tenant', $t->id, null, null, $t->id, null);
                }

                continue;
            }
            $due = $t->status === 'trial' ? $t->trial_ends_at : $t->paid_until;
            if (! $due) {
                continue;
            }
            if ($due->lte(now()->addDays(5)) && $t->plan && $t->plan->price_monthly > 0 && $t->status !== 'trial') {
                $this->invoiceFor($t);
                $out['invoiced']++;
            }
            if ($due->isPast()) {
                $t->forceFill(['status' => 'grace', 'grace_ends_at' => $due->copy()->addDays(self::GRACE_DAYS)])->save();
                $out['grace']++;
                if ($t->grace_ends_at->isPast()) {
                    $t->forceFill(['status' => 'suspended'])->save();
                    $out['suspended']++;
                    Audit::log('billing.suspended', 'tenant', $t->id, null, null, $t->id, null);
                }
            }
        }

        return $out;
    }
}
