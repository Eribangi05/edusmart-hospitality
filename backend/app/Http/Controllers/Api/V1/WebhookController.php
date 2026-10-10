<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain\Payment;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Billing\BillingService;
use App\Services\Payments\PaymentService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * MTN MoMo callback. The body is never trusted: we only use it to find the payment,
     * then ask MTN for the real status. The URL carries a secret token.
     */
    public function momo(Request $r, string $token, PaymentService $pay, BillingService $billing)
    {
        $expected = (string) config('momo.webhook_token');
        abort_unless($expected !== '' && hash_equals($expected, $token), 404);
        $ref = $r->input('referenceId') ?? $r->header('X-Reference-Id') ?? $r->input('financialTransactionId');
        if (! $ref) {
            return response()->json(['ok' => true]);
        }
        $payment = Payment::withoutGlobalScopes()->where('reference', $ref)->first();
        if ($payment) {
            app(Tenancy::class)->run(Tenant::findOrFail($payment->tenant_id), fn () => $pay->refresh($payment));
        } elseif ($inv = Invoice::where('payment_reference', $ref)->first()) {
            $billing->refresh($inv);
        }

        return response()->json(['ok' => true]);
    }
}
