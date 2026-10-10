<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Domain\Folio;
use App\Models\Domain\FolioEntry;
use App\Models\Domain\Guest;
use App\Models\Domain\Payment;
use App\Models\Domain\Reservation;
use App\Models\Domain\Room;
use App\Models\Domain\RoomType;
use App\Services\CustomFields;
use App\Services\Payments\PaymentService;
use App\Services\Rooms\FolioService;
use App\Services\Rooms\RoomService;
use App\Support\ResourceRegistry;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomsController extends Controller
{
    public function __construct(private RoomService $rooms, private FolioService $folios) {}

    private function dateRules(): array
    {
        return ['check_in' => 'required|date', 'check_out' => 'required|date|after:check_in'];
    }

    public function availability(Request $r)
    {
        $d = $r->validate(['room_type_id' => ['required', 'uuid', ResourceRegistry::ref('room_types')]] + $this->dateRules());
        $in = Carbon::parse($d['check_in']);
        $out = Carbon::parse($d['check_out']);

        return response()->json(['data' => ['free' => $this->rooms->freeCount($d['room_type_id'], $in, $out)] + $this->rooms->quote($d['room_type_id'], $in, $out, $r->query('rate_plan_id'))]);
    }

    public function index(Request $r)
    {
        $q = Reservation::query()->orderByDesc('check_in');
        foreach (['status', 'room_id', 'guest_id', 'property_id', 'room_type_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }
        if ($r->filled('from')) {
            $q->where('check_out', '>', $r->query('from'));
        }
        if ($r->filled('to')) {
            $q->where('check_in', '<', $r->query('to'));
        }
        if ($r->filled('q')) {
            $t = '%'.addcslashes($r->query('q'), '%_\\').'%';
            $q->where(fn ($w) => $w->where('number', 'like', $t)->orWhereIn('guest_id', Guest::where('name', 'like', $t)->orWhere('phone', 'like', $t)->select('id')));
        }
        if ($r->filled('updated_since')) {
            $q->where('updated_at', '>', $r->query('updated_since'));
        }
        $p = $q->paginate(min((int) $r->query('per_page', 50), 200));

        return response()->json(['data' => $p->items(), 'meta' => ['page' => $p->currentPage(), 'total' => $p->total()]]);
    }

    public function show(string $id)
    {
        $res = Reservation::findOrFail($id);
        $folio = Folio::where('reservation_id', $id)->first();

        return response()->json(['data' => $res, 'guest' => $res->guest_id ? Guest::find($res->guest_id) : null, 'folio' => $folio ? $this->folioView($folio) : null]);
    }

    public function store(Request $r)
    {
        $t = app(Tenancy::class)->id();
        $d = $r->validate($this->dateRules() + [
            'id' => 'nullable|uuid', 'number' => 'nullable|string|max:40', 'room_type_id' => ['required', 'uuid', ResourceRegistry::ref('room_types')],
            'room_id' => ['nullable', 'uuid', ResourceRegistry::ref('rooms')], 'guest_id' => ['nullable', 'uuid', ResourceRegistry::ref('guests')],
            'guest' => 'nullable|array', 'guest.name' => 'required_with:guest|string|max:150', 'guest.phone' => 'nullable|string|max:30', 'guest.email' => 'nullable|email',
            'guest.nationality' => 'nullable|string', 'guest.id_type' => 'nullable|string', 'guest.id_number' => 'nullable|string',
            'rate_plan_id' => ['nullable', 'uuid', ResourceRegistry::ref('rate_plans')], 'adults' => 'integer|min:1|max:20', 'children' => 'integer|min:0|max:20',
            'status' => 'in:pending,confirmed', 'source' => 'nullable|string|max:30', 'deposit' => 'integer|min:0', 'notes' => 'nullable|string', 'meta' => 'nullable|array',
            'property_id' => ['nullable', 'uuid', Rule::exists('properties', 'id')->where('tenant_id', $t)],
        ]);
        $d['meta'] = CustomFields::validate('reservation', $d['meta'] ?? null, true);
        if (! empty($d['id']) && Reservation::withTrashed()->where('id', $d['id'])->exists()) {
            return response()->json(['data' => Reservation::findOrFail($d['id'])], 200); // idempotent replay
        }
        $res = DB::transaction(function () use ($d) {
            if (! empty($d['guest'])) {
                $g = new Guest;
                $g->forceFill($d['guest'])->save();
                $d['guest_id'] = $g->id;
            }

            return $this->rooms->create($d);
        });

        return response()->json(['data' => $res], 201);
    }

    public function update(Request $r, string $id)
    {
        $res = Reservation::findOrFail($id);
        if (! in_array($res->status, ['pending', 'confirmed'], true)) {
            throw new ApiError('invalid_state', 'Only pending or confirmed reservations can be modified.', 409);
        }
        $d = $r->validate(['check_in' => 'sometimes|date', 'check_out' => 'sometimes|date', 'adults' => 'integer|min:1', 'children' => 'integer|min:0', 'notes' => 'nullable|string',
            'room_type_id' => ['sometimes', 'uuid', ResourceRegistry::ref('room_types')], 'deposit' => 'integer|min:0', 'meta' => 'nullable|array', 'guest_id' => ['nullable', 'uuid', ResourceRegistry::ref('guests')]]);
        $in = Carbon::parse($d['check_in'] ?? $res->check_in);
        $out = Carbon::parse($d['check_out'] ?? $res->check_out);
        $type = $d['room_type_id'] ?? $res->room_type_id;
        DB::transaction(function () use ($res, $d, $in, $out, $type) {
            if ($out->lte($in)) {
                throw new ApiError('validation_failed', 'Check-out must be after check-in.', 422);
            }
            RoomType::where('id', $type)->lockForUpdate()->firstOrFail();
            if ($this->rooms->freeCount($type, $in, $out, $res->id) < 1) {
                throw new ApiError('no_availability', 'No rooms available for the new dates.', 409);
            }
            $quote = $this->rooms->quote($type, $in, $out, $res->rate_plan_id);
            if ($res->room_id && ($type !== $res->room_type_id || $in->toDateString() !== $res->check_in->toDateString() || $out->toDateString() !== $res->check_out->toDateString())) {
                $res->forceFill(['room_id' => null]); // must be re-assigned against the new dates
            }
            if (array_key_exists('meta', $d)) {
                $d['meta'] = CustomFields::validate('reservation', $d['meta'], false);
            }
            $res->forceFill($d + [])->forceFill(['room_type_id' => $type, 'check_in' => $in->toDateString(), 'check_out' => $out->toDateString(), 'total' => $quote['total']])->save();
        });

        return response()->json(['data' => $res->fresh()]);
    }

    public function assign(Request $r, string $id)
    {
        $d = $r->validate(['room_id' => ['required', 'uuid', ResourceRegistry::ref('rooms')]]);

        return response()->json(['data' => DB::transaction(fn () => $this->rooms->assign(Reservation::findOrFail($id), $d['room_id']))]);
    }

    public function checkIn(Request $r, string $id)
    {
        $override = $r->boolean('override') && $r->user()->can2('rooms.rate_override');
        if ($r->filled('guest')) {
            $g = $r->validate(['guest.id_type' => 'nullable|string', 'guest.id_number' => 'required|string', 'guest.nationality' => 'nullable|string'])['guest'];
            $res = Reservation::findOrFail($id);
            Guest::findOrFail($res->guest_id)->forceFill($g)->save();
        }

        return response()->json(['data' => $this->rooms->checkIn(Reservation::findOrFail($id), $override)]);
    }

    public function checkOut(Request $r, string $id)
    {
        $force = $r->boolean('force') && $r->user()->can2('rooms.folio');

        return response()->json(['data' => $this->rooms->checkOut(Reservation::findOrFail($id), $force)]);
    }

    public function cancel(Request $r, string $id)
    {
        $d = $r->validate(['reason' => 'nullable|string|max:255']);

        return response()->json(['data' => $this->rooms->cancel(Reservation::findOrFail($id), 'cancelled', $d['reason'] ?? null)]);
    }

    public function noShow(Request $r, string $id)
    {
        return response()->json(['data' => $this->rooms->cancel(Reservation::findOrFail($id), 'no_show')]);
    }

    /** Room status board for a date: each room with the current/next reservation. */
    public function board(Request $r)
    {
        $date = Carbon::parse($r->query('date', now()))->toDateString();
        $rooms = Room::where('is_active', true)->when($r->filled('property_id'), fn ($q) => $q->where('property_id', $r->query('property_id')))->orderBy('number')->get();
        $res = Reservation::whereIn('status', RoomService::BLOCKING)->whereNotNull('room_id')->where('check_in', '<=', $date)->where('check_out', '>', $date)->get()->keyBy('room_id');

        return response()->json(['data' => $rooms->map(fn ($room) => $room->toArray() + [
            'occupancy' => ! ($x = $res->get($room->id)) ? 'vacant' : ($x->status === 'checked_in' ? 'occupied' : 'reserved'),
            'reservation' => $x ? ['id' => $x->id, 'number' => $x->number, 'guest_id' => $x->guest_id, 'check_in' => $x->check_in->toDateString(), 'check_out' => $x->check_out->toDateString(), 'status' => $x->status] : null,
        ])->values(), 'date' => $date]);
    }

    public function setRoomStatus(Request $r, string $id)
    {
        $d = $r->validate(['housekeeping_status' => 'required|in:clean,dirty,inspected,out_of_order']);
        $room = Room::findOrFail($id);
        $room->forceFill($d)->save();

        return response()->json(['data' => $room]);
    }

    public function nightAudit(Request $r)
    {
        $d = $r->validate(['date' => 'nullable|date']);
        $n = $this->rooms->nightAudit(isset($d['date']) ? Carbon::parse($d['date']) : null);

        return response()->json(['data' => ['posted' => $n]]);
    }

    // ---- folios ----
    private function folioView(Folio $f): array
    {
        $entries = FolioEntry::where('folio_id', $f->id)->orderBy('created_at')->get();

        return $f->toArray() + ['entries' => $entries, 'balance' => $this->folios->balance($f), 'charges' => (int) $entries->whereNull('voided_at')->where('amount', '>', 0)->sum('amount'),
            'payments' => (int) -$entries->whereNull('voided_at')->where('amount', '<', 0)->sum('amount')];
    }

    public function folios(Request $r)
    {
        $q = Folio::query()->orderByDesc('created_at');
        foreach (['reservation_id', 'status', 'guest_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }

        return response()->json(['data' => $q->limit(200)->get()]);
    }

    public function folio(string $id)
    {
        return response()->json(['data' => $this->folioView(Folio::findOrFail($id))]);
    }

    public function postEntry(Request $r, string $id)
    {
        $d = $r->validate(['type' => 'required|in:charge,adjustment,discount', 'description' => 'required|string|max:200', 'amount' => 'required|integer|min:1', 'quantity' => 'numeric|gt:0',
            'catalog_item_id' => ['nullable', 'uuid', ResourceRegistry::ref('catalog_items')], 'idempotency_key' => 'nullable|string|max:80']);
        $folio = Folio::findOrFail($id);
        $e = $this->folios->post($folio, $d['type'], $d['description'], $d['amount'], $d['quantity'] ?? 1, ['idempotency_key' => $d['idempotency_key'] ?? null]);

        return response()->json(['data' => $e, 'balance' => $this->folios->balance($folio)], 201);
    }

    public function payFolio(Request $r, string $id, PaymentService $pay)
    {
        $d = $r->validate(['amount' => 'required|integer|min:1', 'method_id' => ['nullable', 'uuid', ResourceRegistry::ref('payment_methods')], 'method_type' => 'nullable|string',
            'reference' => 'nullable|string|max:100', 'payer_phone' => 'nullable|string|max:20', 'idempotency_key' => 'nullable|string|max:80']);
        $folio = Folio::findOrFail($id);
        $p = $pay->record($d + ['folio_id' => $folio->id]);

        return response()->json(['data' => $p, 'balance' => $this->folios->balance($folio)], 201);
    }

    public function voidEntry(Request $r, string $id)
    {
        $d = $r->validate(['reason' => 'required|string|max:200']);
        $e = $this->folios->void(FolioEntry::findOrFail($id), $d['reason']);
        if ($e->source_type === 'payment') {
            Payment::where('id', $e->source_id)->update(['status' => 'void']);
        }

        return response()->json(['data' => $e, 'balance' => $this->folios->balance(Folio::findOrFail($e->folio_id))]);
    }
}
