<?php

namespace App\Services\Payments;

use App\Exceptions\ApiError;
use App\Models\Domain\Folio;
use App\Models\Domain\Order;
use App\Models\Domain\Payment;
use App\Models\Domain\Shift;
use App\Models\PaymentMethod;
use App\Services\Audit;
use App\Services\Pos\PosService;
use App\Services\Rooms\FolioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records payments against an order, folio or booking. Cash/card/bank are settled
 * immediately; mobile money starts "pending" and settles when the provider confirms.
 */
class PaymentService
{
    public function __construct(private MomoGateway $momo) {}

    public function record(array $d): Payment
    {
        if (! empty($d['idempotency_key']) && ($p = Payment::where('idempotency_key', $d['idempotency_key'])->first())) {
            return $p;
        }
        $method = PaymentMethod::where('is_active', true)->find($d['method_id'] ?? null)
            ?? PaymentMethod::where('is_active', true)->where('type', $d['method_type'] ?? '')->orderBy('position')->first();
        if (! $method) {
            throw new ApiError('validation_failed', 'Unknown or inactive payment method.', 422);
        }
        if ($method->type === 'room_charge') {
            throw new ApiError('validation_failed', 'Use the charge-to-room action for room charges.', 422);
        }

        return DB::transaction(function () use ($d, $method) {
            $order = ! empty($d['order_id']) ? Order::lockForUpdate()->findOrFail($d['order_id']) : null;
            $folio = ! empty($d['folio_id']) ? Folio::findOrFail($d['folio_id']) : null;
            $shift = $order?->shift_id ?? Shift::where('user_id', request()?->user()?->id)->where('status', 'open')->value('id');
            $p = new Payment;
            $p->forceFill([
                'id' => $d['id'] ?? (string) Str::uuid7(), 'property_id' => $order?->property_id ?? $folio?->property_id ?? ($d['property_id'] ?? null),
                'order_id' => $order?->id, 'folio_id' => $folio?->id, 'booking_id' => $d['booking_id'] ?? null,
                'method_id' => $method->id, 'method_type' => $method->type, 'amount' => (int) $d['amount'],
                'reference' => $d['reference'] ?? null, 'payer_phone' => $d['payer_phone'] ?? null,
                'status' => 'pending', 'received_by' => request()?->user()?->id, 'shift_id' => $shift,
                'idempotency_key' => $d['idempotency_key'] ?? null,
            ])->save();
            if ($method->type === 'momo' && ! empty($d['payer_phone']) && empty($d['reference'])) {
                $res = $this->momo->requestToPay($p);
                $p->forceFill(['provider' => $res, 'reference' => $res['reference'] ?? null])->save();
            } else {
                $this->settle($p); // cash / card / bank / manually-confirmed momo
            }
            Audit::log('payment.recorded', 'payment', $p->id, null, $p->only(['amount', 'method_type', 'status']));

            return $p->fresh();
        });
    }

    /** Mark successful and apply to the target order / folio. */
    public function settle(Payment $p): void
    {
        if ($p->status === 'successful') {
            return;
        }
        $p->forceFill(['status' => 'successful', 'paid_at' => now()])->save();
        if ($p->folio_id) {
            $folio = Folio::findOrFail($p->folio_id);
            app(FolioService::class)->post($folio, 'payment', 'Payment ('.$p->method_type.')', $p->amount, 1, ['idempotency_key' => 'pay:'.$p->id, 'source_type' => 'payment', 'source_id' => $p->id]);
        }
        if ($p->order_id) {
            $order = Order::findOrFail($p->order_id);
            $paid = (int) Payment::where('order_id', $order->id)->where('status', 'successful')->sum('amount');
            $order->forceFill(['paid' => $paid] + ($paid >= $order->total && $order->total > 0 ? ['status' => 'paid', 'closed_at' => now()] : []))->save();
            if ($order->status === 'paid') {
                app(PosService::class)->onOrderPaid($order);
            }
        }
    }

    public function fail(Payment $p, ?string $reason = null): void
    {
        if ($p->status === 'pending') {
            $p->forceFill(['status' => 'failed', 'provider' => array_merge($p->provider ?? [], ['failure' => $reason])])->save();
        }
    }

    /** Poll the provider for a pending mobile-money payment. */
    public function refresh(Payment $p): Payment
    {
        if ($p->status === 'pending' && $p->method_type === 'momo' && $p->reference) {
            $status = $this->momo->status($p->reference);
            if ($status === 'successful') {
                DB::transaction(fn () => $this->settle($p));
            } elseif ($status === 'failed') {
                $this->fail($p);
            }
        }

        return $p->fresh();
    }
}
