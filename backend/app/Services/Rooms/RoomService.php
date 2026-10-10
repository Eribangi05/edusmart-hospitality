<?php

namespace App\Services\Rooms;

use App\Exceptions\ApiError;
use App\Models\Domain\Folio;
use App\Models\Domain\FolioEntry;
use App\Models\Domain\Guest;
use App\Models\Domain\RatePlan;
use App\Models\Domain\Reservation;
use App\Models\Domain\Room;
use App\Models\Domain\RoomType;
use App\Models\Domain\WorkTask;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Services\Audit;
use App\Services\Numbering;
use App\Services\Taxes;
use App\Support\Tenancy;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomService
{
    public const BLOCKING = ['pending', 'confirmed', 'checked_in'];

    /** Rooms of a type that can be sold on a given night (excluding self). */
    public function freeCount(string $roomTypeId, Carbon $in, Carbon $out, ?string $exceptReservation = null): int
    {
        $total = Room::where('room_type_id', $roomTypeId)->where('is_active', true)->where('housekeeping_status', '!=', 'out_of_order')->count();
        $min = $total;
        foreach (CarbonPeriod::create($in, $out->copy()->subDay()) as $night) {
            $d = $night->toDateString();
            $booked = Reservation::where('room_type_id', $roomTypeId)->whereIn('status', self::BLOCKING)
                ->where('check_in', '<=', $d)->where('check_out', '>', $d)
                ->when($exceptReservation, fn ($q) => $q->where('id', '!=', $exceptReservation))->count();
            $min = min($min, $total - $booked);
        }

        return max($min, 0);
    }

    /** Best matching rate plan price for each night, falling back to the room type's base price. */
    public function quote(string $roomTypeId, Carbon $in, Carbon $out, ?string $ratePlanId = null): array
    {
        $type = RoomType::findOrFail($roomTypeId);
        $nights = (int) $in->diffInDays($out);
        if ($nights < 1) {
            throw new ApiError('validation_failed', 'Check-out must be after check-in.', 422);
        }
        $plans = RatePlan::where('is_active', true)->where(fn ($q) => $q->whereNull('room_type_id')->orWhere('room_type_id', $roomTypeId))->get();
        $perNight = [];
        $subtotal = 0;
        foreach (CarbonPeriod::create($in, $out->copy()->subDay()) as $night) {
            $cands = $plans->filter(fn ($p) => ($ratePlanId ? $p->id === $ratePlanId : true)
                && (! $p->valid_from || $night->gte($p->valid_from)) && (! $p->valid_to || $night->lte($p->valid_to))
                && (empty($p->days_of_week) || in_array($night->dayOfWeekIso, $p->days_of_week, true)) && $p->min_nights <= $nights);
            $best = $cands->sortByDesc(fn ($p) => [$p->priority, $p->room_type_id ? 1 : 0])->first();
            $price = $best ? $best->price : $type->base_price;
            $perNight[] = ['date' => $night->toDateString(), 'price' => (int) $price, 'rate_plan_id' => $best?->id];
            $subtotal += $price;
        }
        $tax = Taxes::compute($subtotal, TaxRate::where('is_active', true)->where('rate_bp', '>', 0)->get());

        return ['nights' => $nights, 'per_night' => $perNight, 'subtotal' => $subtotal] + $tax;
    }

    public function create(array $d): Reservation
    {
        $in = Carbon::parse($d['check_in'])->startOfDay();
        $out = Carbon::parse($d['check_out'])->startOfDay();

        return DB::transaction(function () use ($d, $in, $out) {
            // serialize bookings per room type to prevent overbooking races (row lock on MySQL/PG)
            $type = RoomType::where('id', $d['room_type_id'])->lockForUpdate()->firstOrFail();
            if ($this->freeCount($type->id, $in, $out) < 1) {
                throw new ApiError('no_availability', 'No rooms of this type are available for these dates.', 409);
            }
            $quote = $this->quote($type->id, $in, $out, $d['rate_plan_id'] ?? null);
            $res = new Reservation;
            $res->forceFill([
                'id' => $d['id'] ?? (string) Str::uuid7(),
                'property_id' => $d['property_id'] ?? $type->property_id,
                'number' => $d['number'] ?? Numbering::next('RSV', Reservation::class),
                'guest_id' => $d['guest_id'] ?? null,
                'room_type_id' => $type->id, 'rate_plan_id' => $d['rate_plan_id'] ?? null,
                'check_in' => $in->toDateString(), 'check_out' => $out->toDateString(),
                'adults' => $d['adults'] ?? 1, 'children' => $d['children'] ?? 0,
                'status' => $d['status'] ?? 'confirmed', 'source' => $d['source'] ?? 'walk_in',
                'total' => $quote['total'], 'deposit' => $d['deposit'] ?? 0, 'notes' => $d['notes'] ?? null, 'meta' => $d['meta'] ?? null,
            ])->save();
            if (! empty($d['room_id'])) {
                $this->assign($res, $d['room_id']);
            }
            $folio = new Folio;
            $folio->forceFill(['property_id' => $res->property_id, 'number' => Numbering::next('FOL', Folio::class), 'reservation_id' => $res->id, 'guest_id' => $res->guest_id, 'status' => 'open'])->save();
            Audit::log('reservation.created', 'reservation', $res->id, null, $res->toArray());

            return $res->fresh();
        });
    }

    public function assign(Reservation $res, string $roomId): Reservation
    {
        $room = Room::findOrFail($roomId);
        if ($room->room_type_id !== $res->room_type_id) {
            throw new ApiError('validation_failed', 'Room belongs to a different room type.', 422);
        }
        if (! $room->is_active || $room->housekeeping_status === 'out_of_order') {
            throw new ApiError('room_unavailable', 'Room is out of order.', 409);
        }
        $clash = Reservation::where('room_id', $room->id)->where('id', '!=', $res->id)->whereIn('status', self::BLOCKING)
            ->where('check_in', '<', $res->check_out->toDateString())->where('check_out', '>', $res->check_in->toDateString())->exists();
        if ($clash) {
            throw new ApiError('room_unavailable', 'Room is already booked for these dates.', 409);
        }
        $res->forceFill(['room_id' => $room->id])->save();

        return $res;
    }

    public function checkIn(Reservation $res, bool $override = false): Reservation
    {
        if (! in_array($res->status, ['confirmed', 'pending'], true)) {
            throw new ApiError('invalid_state', "Cannot check in a {$res->status} reservation.", 409);
        }
        if (! $res->room_id) {
            throw new ApiError('room_required', 'Assign a room before check-in.', 422);
        }
        $room = Room::findOrFail($res->room_id);
        if (! $override && ! in_array($room->housekeeping_status, ['clean', 'inspected'], true)) {
            throw new ApiError('room_not_ready', 'Room is not ready (needs cleaning).', 409);
        }
        $needId = Setting::whereNull('property_id')->where('key', 'rooms.require_id_on_checkin')->first()?->value;
        if ($needId) {
            $g = $res->guest_id ? Guest::find($res->guest_id) : null;
            if (! $g || ! $g->id_number) {
                throw new ApiError('id_required', 'Guest ID details are required at check-in.', 422);
            }
        }
        $res->forceFill(['status' => 'checked_in', 'checked_in_at' => now()])->save();
        Audit::log('reservation.checked_in', 'reservation', $res->id);

        return $res;
    }

    public function checkOut(Reservation $res, bool $force = false): Reservation
    {
        if ($res->status !== 'checked_in') {
            throw new ApiError('invalid_state', 'Only checked-in reservations can be checked out.', 409);
        }
        $folio = Folio::where('reservation_id', $res->id)->first();
        $balance = $folio ? app(FolioService::class)->balance($folio) : 0;
        if ($balance > 0 && ! $force) {
            throw new ApiError('balance_due', 'Folio has an outstanding balance.', 409, ['balance' => $balance]);
        }
        DB::transaction(function () use ($res, $folio) {
            $res->forceFill(['status' => 'checked_out', 'checked_out_at' => now()])->save();
            $folio?->forceFill(['status' => 'closed'])->save();
            if ($res->room_id) {
                Room::where('id', $res->room_id)->update(['housekeeping_status' => 'dirty']);
                if (in_array('housekeeping', app(Tenancy::class)->get()->enabledModules(), true)) {
                    $room = Room::find($res->room_id);
                    $t = new WorkTask;
                    $t->forceFill(['property_id' => $res->property_id, 'type' => 'clean', 'room_id' => $res->room_id, 'title' => 'Clean room '.$room?->number.' (checkout)', 'priority' => 'high', 'status' => 'pending'])->save();
                }
            }
            Audit::log('reservation.checked_out', 'reservation', $res->id);
        });

        return $res->fresh();
    }

    public function cancel(Reservation $res, string $status = 'cancelled', ?string $reason = null): Reservation
    {
        if (! in_array($res->status, ['pending', 'confirmed'], true)) {
            throw new ApiError('invalid_state', "Cannot change a {$res->status} reservation.", 409);
        }
        $res->forceFill(['status' => $status, 'notes' => trim(($res->notes ?? '')."\n".($reason ?? ''))])->save();
        Audit::log("reservation.$status", 'reservation', $res->id, null, ['reason' => $reason]);

        return $res;
    }

    /** Post one night's room charge (+taxes) for every in-house reservation. Idempotent per night. */
    public function nightAudit(?Carbon $date = null): int
    {
        $date ??= now()->startOfDay();
        $posted = 0;
        foreach (Reservation::where('status', 'checked_in')->where('check_in', '<=', $date->toDateString())->where('check_out', '>', $date->toDateString())->get() as $res) {
            $folio = Folio::where('reservation_id', $res->id)->first();
            if (! $folio) {
                continue;
            }
            $q = $this->quote($res->room_type_id, $date->copy(), $date->copy()->addDay(), $res->rate_plan_id);
            $key = 'night:'.$res->id.':'.$date->toDateString();
            if (FolioEntry::where('idempotency_key', $key)->exists()) {
                continue;
            }
            app(FolioService::class)->post($folio, 'charge', 'Room charge '.$date->toDateString(), $q['net'], 1, ['idempotency_key' => $key, 'business_date' => $date->toDateString(), 'source_type' => 'night_audit', 'source_id' => $res->id]);
            foreach ($q['taxes'] as $t) {
                if ($t['amount'] > 0) {
                    app(FolioService::class)->post($folio, 'tax', $t['name'], $t['amount'], 1, ['idempotency_key' => $key.':'.$t['key'], 'business_date' => $date->toDateString(), 'source_type' => 'night_audit', 'source_id' => $res->id]);
                }
            }
            $posted++;
        }

        return $posted;
    }
}
