<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Domain\Folio;
use App\Models\Domain\Order;
use App\Models\Domain\OrderItem;
use App\Models\Domain\Payment;
use App\Models\Domain\Reservation;
use App\Models\Domain\Shift;
use App\Services\Payments\PaymentService;
use App\Services\Pos\PosService;
use App\Support\ResourceRegistry;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(private PosService $pos) {}

    private function itemRules(): array
    {
        return ['items' => 'array', 'items.*.id' => 'nullable|uuid', 'items.*.catalog_item_id' => ['required', 'uuid', ResourceRegistry::ref('catalog_items')], 'items.*.quantity' => 'numeric|gt:0|max:999',
            'items.*.modifiers' => 'nullable|array', 'items.*.notes' => 'nullable|string|max:200', 'items.*.unit_price' => 'nullable|integer|min:0'];
    }

    private function view(Order $o): array
    {
        return $o->toArray() + ['items' => OrderItem::where('order_id', $o->id)->orderBy('created_at')->get(), 'payments' => Payment::where('order_id', $o->id)->get(), 'balance' => max($o->total - $o->paid, 0)];
    }

    public function index(Request $r)
    {
        $q = Order::query()->orderByDesc('created_at');
        foreach (['status', 'table_id', 'type', 'shift_id', 'property_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            if ($r->filled($k)) {
                $q->where('created_at', $op, $r->query($k));
            }
        }
        if ($r->filled('updated_since')) {
            $q->where('updated_at', '>', $r->query('updated_since'));
        }
        $p = $q->paginate(min((int) $r->query('per_page', 50), 200));

        return response()->json(['data' => $p->items(), 'meta' => ['page' => $p->currentPage(), 'total' => $p->total()]]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => $this->view(Order::findOrFail($id))]);
    }

    public function store(Request $r)
    {
        $t = app(Tenancy::class)->id();
        $d = $r->validate($this->itemRules() + ['id' => 'nullable|uuid', 'number' => 'nullable|string|max:40', 'type' => 'in:dine_in,takeaway,room_service,retail,delivery',
            'table_id' => ['nullable', 'uuid', ResourceRegistry::ref('dining_tables')], 'guests' => 'integer|min:1', 'customer_name' => 'nullable|string|max:120', 'guest_id' => ['nullable', 'uuid', ResourceRegistry::ref('guests')],
            'property_id' => ['nullable', 'uuid', Rule::exists('properties', 'id')->where('tenant_id', $t)], 'meta' => 'nullable|array']);
        if (! empty($d['id']) && ($o = Order::withTrashed()->find($d['id']))) {
            return response()->json(['data' => $this->view($o)]); // idempotent replay from an offline outbox
        }

        return response()->json(['data' => $this->view($this->pos->create($d))], 201);
    }

    public function addItems(Request $r, string $id)
    {
        $d = $r->validate($this->itemRules());

        return response()->json(['data' => $this->view($this->pos->addItems(Order::findOrFail($id), $d['items'] ?? []))]);
    }

    public function send(string $id)
    {
        $order = Order::findOrFail($id);
        $tickets = $this->pos->send($order);

        return response()->json(['data' => $this->view($order->fresh()), 'tickets' => $tickets]);
    }

    public function voidItem(Request $r, string $id)
    {
        $d = $r->validate(['reason' => 'required|string|max:200', 'manager_pin' => 'nullable|digits_between:4,6']);

        return response()->json(['data' => $this->view($this->pos->voidItem(OrderItem::findOrFail($id), $d['reason'], $d['manager_pin'] ?? null))]);
    }

    public function voidOrder(Request $r, string $id)
    {
        $d = $r->validate(['reason' => 'required|string|max:200', 'manager_pin' => 'nullable|digits_between:4,6']);

        return response()->json(['data' => $this->view($this->pos->voidOrder(Order::findOrFail($id), $d['reason'], $d['manager_pin'] ?? null))]);
    }

    public function discount(Request $r, string $id)
    {
        $d = $r->validate(['amount' => 'nullable|integer|min:0', 'percent_bp' => 'nullable|integer|min:0|max:10000', 'manager_pin' => 'nullable|digits_between:4,6']);
        if (! isset($d['amount']) && ! isset($d['percent_bp'])) {
            throw new ApiError('validation_failed', 'Provide amount or percent_bp.', 422);
        }

        return response()->json(['data' => $this->view($this->pos->applyDiscount(Order::findOrFail($id), $d['amount'] ?? null, $d['percent_bp'] ?? null, $d['manager_pin'] ?? null))]);
    }

    public function tip(Request $r, string $id)
    {
        $d = $r->validate(['tip' => 'required|integer|min:0']);
        $o = Order::findOrFail($id);
        $this->pos->ensureOpen($o);
        $o->forceFill(['tip' => $d['tip']])->save();

        return response()->json(['data' => $this->view($this->pos->recalc($o))]);
    }

    public function split(Request $r, string $id)
    {
        $d = $r->validate(['item_ids' => 'required|array|min:1', 'item_ids.*' => 'uuid']);
        $new = $this->pos->split(Order::findOrFail($id), $d['item_ids']);

        return response()->json(['data' => $this->view($new), 'original' => $this->view(Order::findOrFail($id))], 201);
    }

    public function merge(Request $r, string $id)
    {
        $d = $r->validate(['from_order_id' => ['required', 'uuid', ResourceRegistry::ref('orders')]]);

        return response()->json(['data' => $this->view($this->pos->merge(Order::findOrFail($id), Order::findOrFail($d['from_order_id'])))]);
    }

    public function pay(Request $r, string $id, PaymentService $pay)
    {
        $d = $r->validate(['amount' => 'required|integer|min:1', 'method_id' => ['nullable', 'uuid', ResourceRegistry::ref('payment_methods')], 'method_type' => 'nullable|string',
            'reference' => 'nullable|string|max:100', 'payer_phone' => 'nullable|string|max:20', 'idempotency_key' => 'nullable|string|max:80', 'id' => 'nullable|uuid']);
        $order = Order::findOrFail($id);
        $this->pos->ensureOpen($order);
        if ($order->total <= 0) {
            throw new ApiError('invalid_state', 'Nothing to pay.', 409);
        }
        $p = $pay->record($d + ['order_id' => $order->id]);

        return response()->json(['data' => $this->view($order->fresh()), 'payment' => $p], 201);
    }

    public function chargeRoom(Request $r, string $id)
    {
        $d = $r->validate(['reservation_id' => ['required', 'uuid', ResourceRegistry::ref('reservations')]]);
        $res = Reservation::findOrFail($d['reservation_id']);
        if ($res->status !== 'checked_in') {
            throw new ApiError('invalid_state', 'Guest is not checked in.', 409);
        }
        $folio = Folio::where('reservation_id', $res->id)->firstOrFail();

        return response()->json(['data' => $this->view($this->pos->chargeToRoom(Order::findOrFail($id), $folio))]);
    }

    public function payment(string $id, PaymentService $pay)
    {
        return response()->json(['data' => $pay->refresh(Payment::findOrFail($id))]);
    }

    /** Live tickets for kitchen / bar displays. */
    public function tickets(Request $r)
    {
        $station = $r->query('station', 'kitchen');
        $lines = OrderItem::where('station', $station)->whereIn('status', ['sent', 'preparing', 'ready'])->orderBy('created_at')->get();
        $orders = Order::whereIn('id', $lines->pluck('order_id'))->get()->keyBy('id');

        return response()->json(['data' => $lines->groupBy('order_id')->map(fn ($g, $oid) => ['order' => $orders[$oid] ?? null, 'items' => $g->values()])->values()]);
    }

    public function itemStatus(Request $r, string $id)
    {
        $d = $r->validate(['status' => 'required|in:preparing,ready,served']);
        $line = OrderItem::findOrFail($id);
        if ($line->status === 'void') {
            throw new ApiError('invalid_state', 'Item is void.', 409);
        }
        $line->forceFill($d)->save();

        return response()->json(['data' => $line]);
    }

    // ---- shifts ----
    public function currentShift(Request $r)
    {
        return response()->json(['data' => Shift::where('user_id', $r->user()->id)->where('status', 'open')->first()]);
    }

    public function openShift(Request $r)
    {
        $d = $r->validate(['opening_float' => 'required|integer|min:0', 'device_id' => 'nullable|uuid']);

        return response()->json(['data' => $this->pos->openShift($d['opening_float'], $d['device_id'] ?? null)], 201);
    }

    public function closeShift(Request $r, string $id)
    {
        $d = $r->validate(['counted_cash' => 'required|integer|min:0']);
        $shift = Shift::findOrFail($id);
        if ($shift->user_id !== $r->user()->id && ! $r->user()->can2('pos.close_shift')) {
            throw new ApiError('forbidden', 'You cannot close another cashier\'s shift.', 403);
        }

        return response()->json(['data' => $this->pos->closeShift($shift, $d['counted_cash'])]);
    }
}
