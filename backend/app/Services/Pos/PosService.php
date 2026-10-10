<?php

namespace App\Services\Pos;

use App\Exceptions\ApiError;
use App\Models\Domain\CatalogItem;
use App\Models\Domain\Folio;
use App\Models\Domain\Order;
use App\Models\Domain\OrderItem;
use App\Models\Domain\Payment;
use App\Models\Domain\Shift;
use App\Models\Domain\Voucher;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Audit;
use App\Services\Inventory\InventoryService;
use App\Services\Numbering;
use App\Services\Rooms\FolioService;
use App\Services\Taxes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosService
{
    public function setting(string $key, $default = null)
    {
        $s = Setting::whereNull('property_id')->where('key', $key)->first();

        return $s ? $s->value : $default;
    }

    public function create(array $d): Order
    {
        return DB::transaction(function () use ($d) {
            $order = new Order;
            $order->forceFill([
                'id' => $d['id'] ?? (string) Str::uuid7(), 'property_id' => $d['property_id'] ?? null,
                'number' => $d['number'] ?? Numbering::next('ORD', Order::class),
                'type' => $d['type'] ?? 'dine_in', 'table_id' => $d['table_id'] ?? null, 'guests' => $d['guests'] ?? 1,
                'customer_name' => $d['customer_name'] ?? null, 'guest_id' => $d['guest_id'] ?? null, 'status' => 'open',
                'opened_by' => request()?->user()?->id, 'meta' => $d['meta'] ?? null,
                'shift_id' => Shift::where('user_id', request()?->user()?->id)->where('status', 'open')->value('id'),
            ])->save();
            $this->addItems($order, $d['items'] ?? []);
            Audit::log('order.created', 'order', $order->id);

            return $order->fresh();
        });
    }

    public function addItems(Order $order, array $items): Order
    {
        $this->ensureOpen($order);
        foreach ($items as $i) {
            $item = CatalogItem::where('is_active', true)->findOrFail($i['catalog_item_id']);
            $qty = (float) ($i['quantity'] ?? 1);
            $mods = $i['modifiers'] ?? [];
            $unit = $item->price + (int) array_sum(array_map(fn ($m) => (int) ($m['price'] ?? 0), $mods));
            if (isset($i['unit_price']) && (int) $i['unit_price'] !== $unit) {
                if (! request()->user()->can2('pos.price_override')) {
                    throw new ApiError('forbidden', 'You cannot override prices.', 403);
                }
                $unit = (int) $i['unit_price'];
            }
            $rates = $item->tax_rate_ids !== null ? Taxes::activeRates($item->tax_rate_ids) : Taxes::activeRates();
            $row = new OrderItem;
            $row->forceFill([
                'id' => $i['id'] ?? (string) Str::uuid7(), 'property_id' => $order->property_id, 'order_id' => $order->id, 'catalog_item_id' => $item->id,
                'name' => $item->name, 'quantity' => $qty, 'unit_price' => $unit, 'modifiers' => $mods ?: null, 'notes' => $i['notes'] ?? null,
                'station' => $item->station, 'status' => 'new', 'tax_bp' => (int) $rates->sum('rate_bp'),
                'tax_inclusive' => (bool) $rates->where('inclusive', true)->count(), 'line_total' => (int) round($unit * $qty),
                'meta' => ['tax_rate_ids' => $rates->pluck('id')->all()],
            ])->save();
        }

        return $this->recalc($order);
    }

    /** Server-side totals. Discounts reduce the net before tax; service charge applies to the discounted net. */
    public function recalc(Order $order): Order
    {
        $lines = OrderItem::where('order_id', $order->id)->whereNull('voided_at')->get();
        $gross = (int) $lines->sum('line_total');
        $discount = min((int) ($order->discount ?? 0), $gross);
        $subtotal = 0;
        $tax = 0;
        foreach ($lines as $l) {
            $share = $gross > 0 ? (int) round($discount * $l->line_total / $gross) : 0;
            $rates = TaxRate::whereIn('id', $l->meta['tax_rate_ids'] ?? [])->get();
            $c = Taxes::compute($l->line_total - $share, $rates);
            $subtotal += $c['net'];
            $tax += $c['tax_total'];
        }
        $serviceBp = (int) $this->setting('pos.service_charge_bp', 0);
        $service = (int) round($subtotal * $serviceBp / 10000);
        $order->forceFill(['subtotal' => $subtotal, 'tax' => $tax, 'service' => $service, 'total' => $subtotal + $tax + $service + (int) $order->tip])->save();

        return $order->fresh();
    }

    public function ensureOpen(Order $order): void
    {
        if (in_array($order->status, ['paid', 'void'], true)) {
            throw new ApiError('invalid_state', "Order is {$order->status}.", 409);
        }
    }

    /** Voids and discounts need pos.void / pos.discount, or the PIN of a user who has it. */
    public function authorizeSensitive(string $permission, ?string $pin): void
    {
        $user = request()->user();
        if ($user->can2($permission)) {
            return;
        }
        if ($pin) {
            foreach (User::where('is_active', true)->whereNotNull('pin_hash')->get() as $u) {
                if ($u->can2($permission) && Hash::check($pin, $u->pin_hash)) {
                    Audit::log('pos.manager_override', 'user', $u->id, null, ['permission' => $permission]);

                    return;
                }
            }
        }
        throw new ApiError('manager_required', 'Manager approval is required.', 403, ['permission' => $permission]);
    }

    public function voidItem(OrderItem $line, string $reason, ?string $pin): Order
    {
        $order = Order::findOrFail($line->order_id);
        $this->ensureOpen($order);
        if ($line->status !== 'new') { // already sent to the kitchen/bar
            $this->authorizeSensitive('pos.void', $pin);
        }
        $line->forceFill(['voided_at' => now(), 'status' => 'void', 'void_reason' => $reason])->save();
        Audit::log('order.item_voided', 'order_item', $line->id, null, ['reason' => $reason, 'amount' => $line->line_total]);

        return $this->recalc($order);
    }

    public function voidOrder(Order $order, string $reason, ?string $pin): Order
    {
        $this->authorizeSensitive('pos.void', $pin);
        if (Payment::where('order_id', $order->id)->where('status', 'successful')->exists()) {
            throw new ApiError('invalid_state', 'Refund payments before voiding a paid order.', 409);
        }
        $order->forceFill(['status' => 'void', 'void_reason' => $reason, 'closed_at' => now()])->save();
        OrderItem::where('order_id', $order->id)->update(['status' => 'void']);
        Audit::log('order.voided', 'order', $order->id, null, ['reason' => $reason, 'total' => $order->total]);

        return $order;
    }

    public function applyDiscount(Order $order, ?int $amount, ?int $percentBp, ?string $pin): Order
    {
        $this->ensureOpen($order);
        $this->authorizeSensitive('pos.discount', $pin);
        $gross = (int) OrderItem::where('order_id', $order->id)->whereNull('voided_at')->sum('line_total');
        $d = $amount ?? (int) round($gross * ($percentBp ?? 0) / 10000);
        $order->forceFill(['discount' => min($d, $gross)])->save();
        Audit::log('order.discount', 'order', $order->id, null, ['discount' => $order->discount]);

        return $this->recalc($order);
    }

    /** Promo codes: validated here, applied as an order discount (before tax). One code per order. */
    public function applyVoucher(Order $order, string $code): Order
    {
        $this->ensureOpen($order);
        $v = Voucher::where('code', strtoupper(trim($code)))->orWhere('code', trim($code))->first();
        if (! $v || ! $v->is_active) {
            throw new ApiError('voucher_invalid', 'This code is not valid.', 422);
        }
        if (($order->meta['voucher'] ?? null) === $v->code) {
            return $order; // already applied to this order
        }
        $today = now()->toDateString();
        if (($v->valid_from && $today < $v->valid_from->toDateString()) || ($v->valid_to && $today > $v->valid_to->toDateString())) {
            throw new ApiError('voucher_expired', 'This code is outside its validity dates.', 422);
        }
        if ($v->max_uses !== null && $v->uses >= $v->max_uses) {
            throw new ApiError('voucher_used_up', 'This code has been used the maximum number of times.', 422);
        }
        $gross = (int) OrderItem::where('order_id', $order->id)->whereNull('voided_at')->sum('line_total');
        if ($gross < $v->min_total) {
            throw new ApiError('voucher_min_total', 'Minimum order total for this code is '.number_format($v->min_total).' RWF.', 422);
        }
        $discount = $v->kind === 'percent' ? (int) round($gross * min($v->value, 100) / 100) : (int) $v->value; // percent is a whole number: 10 = 10%
        DB::transaction(function () use ($order, $v, $discount, $gross) {
            if (! empty($order->meta['voucher'])) { // replacing an earlier code releases it
                Voucher::where('code', $order->meta['voucher'])->where('uses', '>', 0)->decrement('uses');
            }
            $v->increment('uses');
            $order->forceFill(['discount' => min($discount, $gross), 'meta' => array_merge($order->meta ?? [], ['voucher' => $v->code])])->save();
        });
        Audit::log('order.voucher', 'order', $order->id, null, ['code' => $v->code, 'discount' => $order->discount]);

        return $this->recalc($order);
    }

    public function send(Order $order): array
    {
        $this->ensureOpen($order);
        $lines = OrderItem::where('order_id', $order->id)->where('status', 'new')->get();
        foreach ($lines as $l) {
            $l->forceFill(['status' => $l->station === 'none' ? 'served' : 'sent'])->save();
        }
        if ($lines->isNotEmpty() && $order->status === 'open') {
            $order->forceFill(['status' => 'sent'])->save();
        }

        return $lines->groupBy('station')->map(fn ($g) => $g->values())->all();
    }

    /** Move selected lines to a new order (split bill) . */
    public function split(Order $order, array $lineIds): Order
    {
        $this->ensureOpen($order);
        $new = new Order;
        $new->forceFill($order->only(['property_id', 'type', 'table_id', 'shift_id', 'guest_id', 'customer_name']) + [
            'id' => (string) Str::uuid7(), 'number' => Numbering::next('ORD', Order::class), 'status' => 'open', 'opened_by' => request()->user()->id, 'guests' => 1,
        ])->save();
        $n = OrderItem::where('order_id', $order->id)->whereIn('id', $lineIds)->update(['order_id' => $new->id]);
        if ($n !== count($lineIds)) {
            throw new ApiError('validation_failed', 'Some lines do not belong to this order.', 422);
        }
        $this->recalc($order);

        return $this->recalc($new);
    }

    public function merge(Order $into, Order $from): Order
    {
        $this->ensureOpen($into);
        $this->ensureOpen($from);
        OrderItem::where('order_id', $from->id)->update(['order_id' => $into->id]);
        $from->forceFill(['status' => 'void', 'void_reason' => 'merged into '.$into->number, 'closed_at' => now()])->save();
        $this->recalc($from);

        return $this->recalc($into);
    }

    /** Charge the order to a guest's open room folio (net + taxes posted as separate entries). */
    public function chargeToRoom(Order $order, Folio $folio): Order
    {
        $this->ensureOpen($order);
        if ($folio->status !== 'open') {
            throw new ApiError('folio_closed', 'Folio is closed.', 409);
        }
        DB::transaction(function () use ($order, $folio) {
            $fs = app(FolioService::class);
            $desc = 'Restaurant/Bar '.$order->number;
            $fs->post($folio, 'charge', $desc, $order->subtotal + $order->service, 1, ['idempotency_key' => 'order:'.$order->id, 'source_type' => 'order', 'source_id' => $order->id]);
            if ($order->tax > 0) {
                $fs->post($folio, 'tax', 'Taxes '.$order->number, $order->tax, 1, ['idempotency_key' => 'order:'.$order->id.':tax', 'source_type' => 'order', 'source_id' => $order->id]);
            }
            $method = PaymentMethod::where('type', 'room_charge')->first();
            $p = new Payment;
            $p->forceFill(['property_id' => $order->property_id, 'order_id' => $order->id, 'folio_id' => $folio->id, 'method_id' => $method?->id, 'method_type' => 'room_charge',
                'amount' => $order->total, 'status' => 'successful', 'paid_at' => now(), 'received_by' => request()->user()->id, 'shift_id' => $order->shift_id])->save();
            $order->forceFill(['status' => 'paid', 'paid' => $order->total, 'folio_id' => $folio->id, 'closed_at' => now()])->save();
            $this->onOrderPaid($order);
        });

        return $order->fresh();
    }

    public function onOrderPaid(Order $order): void
    {
        app(InventoryService::class)->deductForOrder($order);
        OrderItem::where('order_id', $order->id)->whereNull('voided_at')->update(['status' => 'served']);
    }

    public function openShift(int $float, ?string $deviceId): Shift
    {
        $uid = request()->user()->id;
        if (Shift::where('user_id', $uid)->where('status', 'open')->exists()) {
            throw new ApiError('shift_open', 'You already have an open shift.', 409);
        }
        $s = new Shift;
        $s->forceFill(['user_id' => $uid, 'device_id' => $deviceId, 'opened_at' => now(), 'opening_float' => $float, 'status' => 'open'])->save();

        return $s;
    }

    public function closeShift(Shift $s, int $counted): Shift
    {
        if ($s->status !== 'open') {
            throw new ApiError('invalid_state', 'Shift is already closed.', 409);
        }
        $cash = (int) Payment::where('shift_id', $s->id)->where('method_type', 'cash')->where('status', 'successful')->sum('amount');
        $expected = $s->opening_float + $cash;
        $s->forceFill(['closed_at' => now(), 'status' => 'closed', 'expected_cash' => $expected, 'counted_cash' => $counted, 'variance' => $counted - $expected])->save();
        Audit::log('shift.closed', 'shift', $s->id, null, ['expected' => $expected, 'counted' => $counted]);

        return $s;
    }
}
