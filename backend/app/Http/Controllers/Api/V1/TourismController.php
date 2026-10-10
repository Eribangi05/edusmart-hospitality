<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Domain\Booking;
use App\Models\Domain\BookingAssignment;
use App\Models\Domain\BookingLine;
use App\Models\Domain\BookingParticipant;
use App\Models\Domain\TourDeparture;
use App\Services\CustomFields;
use App\Services\Payments\PaymentService;
use App\Services\Tourism\TourismService;
use App\Support\ResourceRegistry;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TourismController extends Controller
{
    public function __construct(private TourismService $svc) {}

    private function view(Booking $b): array
    {
        $folio = $this->svc->folioFor($b);

        return $b->toArray() + [
            'lines' => BookingLine::where('booking_id', $b->id)->get(), 'participants' => BookingParticipant::where('booking_id', $b->id)->get(),
            'assignments' => BookingAssignment::where('booking_id', $b->id)->get(), 'balance' => $this->svc->balance($b), 'folio_id' => $folio?->id,
        ];
    }

    private function bookingRules(): array
    {
        $t = app(Tenancy::class)->id();

        return [
            'catalog_item_id' => ['required', 'uuid', ResourceRegistry::ref('catalog_items')], 'departure_id' => ['nullable', 'uuid', ResourceRegistry::ref('tour_departures')],
            'date' => 'nullable|date', 'start_time' => 'nullable|string|max:5', 'pax_adults' => 'integer|min:1|max:500', 'pax_children' => 'integer|min:0|max:500', 'units' => 'numeric|gt:0',
            'addons' => 'array', 'addons.*.catalog_item_id' => ['required', 'uuid', ResourceRegistry::ref('catalog_items')], 'addons.*.quantity' => 'numeric|gt:0',
            'property_id' => ['nullable', 'uuid', Rule::exists('properties', 'id')->where('tenant_id', $t)],
        ];
    }

    public function quote(Request $r)
    {
        return response()->json(['data' => $this->svc->quote($r->validate($this->bookingRules()))]);
    }

    public function departures(Request $r)
    {
        $q = TourDeparture::query()->orderBy('date')->orderBy('start_time');
        foreach (['catalog_item_id', 'status', 'property_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }
        $q->when($r->filled('from'), fn ($x) => $x->where('date', '>=', $r->query('from')))->when($r->filled('to'), fn ($x) => $x->where('date', '<=', $r->query('to')));

        return response()->json(['data' => $q->limit(500)->get()->map(fn ($d) => $d->toArray() + $this->svc->departureStats($d))]);
    }

    public function manifest(string $id)
    {
        return response()->json(['data' => $this->svc->manifest(TourDeparture::findOrFail($id))]);
    }

    public function index(Request $r)
    {
        $q = Booking::query()->orderByDesc('date');
        foreach (['status', 'catalog_item_id', 'departure_id', 'property_id', 'guest_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }
        $q->when($r->filled('from'), fn ($x) => $x->where('date', '>=', $r->query('from')))->when($r->filled('to'), fn ($x) => $x->where('date', '<=', $r->query('to')))
            ->when($r->filled('updated_since'), fn ($x) => $x->where('updated_at', '>', $r->query('updated_since')))
            ->when($r->filled('q'), fn ($x) => $x->where(fn ($w) => $w->where('number', 'like', '%'.$r->query('q').'%')->orWhere('customer_name', 'like', '%'.$r->query('q').'%')->orWhere('customer_phone', 'like', '%'.$r->query('q').'%')));
        $p = $q->paginate(min((int) $r->query('per_page', 50), 200));

        return response()->json(['data' => $p->items(), 'meta' => ['page' => $p->currentPage(), 'total' => $p->total()]]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => $this->view(Booking::findOrFail($id))]);
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->bookingRules() + [
            'id' => 'nullable|uuid', 'number' => 'nullable|string|max:40', 'customer_name' => 'required|string|max:150', 'customer_phone' => 'nullable|string|max:30', 'customer_email' => 'nullable|email',
            'guest_id' => ['nullable', 'uuid', ResourceRegistry::ref('guests')], 'status' => 'in:inquiry,confirmed', 'source' => 'nullable|string|max:30', 'pickup_location' => 'nullable|string|max:200',
            'notes' => 'nullable|string', 'meta' => 'nullable|array', 'charge_to_reservation_id' => ['nullable', 'uuid', ResourceRegistry::ref('reservations')],
            'participants' => 'array|max:200', 'participants.*.name' => 'required|string|max:150', 'participants.*.nationality' => 'nullable|string|max:60', 'participants.*.id_number' => 'nullable|string|max:60', 'participants.*.age' => 'nullable|integer|min:0|max:120',
        ]);
        $d['meta'] = CustomFields::validate('booking', $d['meta'] ?? null, true);
        if (! empty($d['id']) && ($b = Booking::withTrashed()->find($d['id']))) {
            return response()->json(['data' => $this->view($b)]);
        }

        return response()->json(['data' => $this->view($this->svc->create($d))], 201);
    }

    public function update(Request $r, string $id)
    {
        $b = Booking::findOrFail($id);
        $d = $r->validate(['customer_name' => 'sometimes|string|max:150', 'customer_phone' => 'nullable|string|max:30', 'customer_email' => 'nullable|email', 'pickup_location' => 'nullable|string|max:200',
            'notes' => 'nullable|string', 'start_time' => 'nullable|string|max:5', 'meta' => 'nullable|array',
            'participants' => 'array|max:200', 'participants.*.name' => 'required|string|max:150', 'participants.*.nationality' => 'nullable|string|max:60', 'participants.*.id_number' => 'nullable|string|max:60', 'participants.*.age' => 'nullable|integer']);
        if (in_array($b->status, ['completed', 'cancelled', 'no_show'], true)) {
            throw new ApiError('invalid_state', 'This booking can no longer be edited.', 409);
        }
        if (array_key_exists('meta', $d)) {
            $d['meta'] = CustomFields::validate('booking', $d['meta'], false);
        }
        $participants = $d['participants'] ?? null;
        unset($d['participants']);
        $b->forceFill($d)->save();
        if ($participants !== null) {
            BookingParticipant::where('booking_id', $b->id)->delete();
            foreach ($participants as $p) {
                $bp = new BookingParticipant;
                $bp->forceFill($p + ['property_id' => $b->property_id, 'booking_id' => $b->id])->save();
            }
        }

        return response()->json(['data' => $this->view($b->fresh())]);
    }

    public function transition(Request $r, string $id, string $to)
    {
        $b = Booking::findOrFail($id);
        if ($to === 'cancelled' && $this->svc->balance($b) < $b->total && $b->deposit > 0 && ! $r->user()->can2('finance.reconcile')) {
            // cancelling after payment: allowed, but refunds are handled by finance
            $b->forceFill(['notes' => trim($b->notes."\nCancelled with deposit paid: refund review needed")])->save();
        }

        return response()->json(['data' => $this->view($this->svc->transition($b, $to))]);
    }

    public function assign(Request $r, string $id)
    {
        $d = $r->validate(['resource_id' => ['required', 'uuid', ResourceRegistry::ref('tourism_resources')], 'role' => 'nullable|string|max:40']);
        $this->svc->assign(Booking::findOrFail($id), $d['resource_id'], $d['role'] ?? null);

        return response()->json(['data' => $this->view(Booking::findOrFail($id))]);
    }

    public function unassign(string $id, string $resourceId)
    {
        BookingAssignment::where('booking_id', $id)->where('resource_id', $resourceId)->delete();

        return response()->noContent();
    }

    public function pay(Request $r, string $id, PaymentService $pay)
    {
        $d = $r->validate(['amount' => 'required|integer|min:1', 'method_id' => ['nullable', 'uuid', ResourceRegistry::ref('payment_methods')], 'method_type' => 'nullable|string',
            'reference' => 'nullable|string|max:100', 'payer_phone' => 'nullable|string|max:20', 'idempotency_key' => 'nullable|string|max:80']);
        $b = Booking::findOrFail($id);
        $folio = $this->svc->folioFor($b) ?? throw new ApiError('invalid_state', 'No folio.', 409);
        if ($this->svc->balance($b) < $d['amount']) {
            throw new ApiError('overpayment', 'Amount exceeds the outstanding balance.', 422, ['balance' => $this->svc->balance($b)]);
        }
        $p = $pay->record($d + ['folio_id' => $folio->id, 'booking_id' => $b->id]);
        $this->svc->refreshPaidStatus($b->fresh());

        return response()->json(['data' => $this->view($b->fresh()), 'payment' => $p], 201);
    }

    public function bookingsToday(Request $r)
    {
        $date = $r->query('date', now()->toDateString());
        $b = Booking::where('date', $date)->whereNotIn('status', ['cancelled', 'no_show'])->orderBy('start_time')->get();

        return response()->json(['data' => $b->map(fn ($x) => $this->view($x)), 'date' => $date, 'pax' => (int) $b->sum(fn ($x) => $x->pax_adults + $x->pax_children)]);
    }
}
