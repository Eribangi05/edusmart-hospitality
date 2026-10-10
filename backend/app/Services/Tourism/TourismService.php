<?php

namespace App\Services\Tourism;

use App\Exceptions\ApiError;
use App\Models\Domain\Booking;
use App\Models\Domain\BookingAssignment;
use App\Models\Domain\BookingLine;
use App\Models\Domain\BookingParticipant;
use App\Models\Domain\CatalogItem;
use App\Models\Domain\Folio;
use App\Models\Domain\FolioEntry;
use App\Models\Domain\Payment;
use App\Models\Domain\Reservation;
use App\Models\Domain\TourDeparture;
use App\Models\Domain\TourismResource;
use App\Services\Audit;
use App\Services\Numbering;
use App\Services\Rooms\FolioService;
use App\Services\Taxes;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tours, activities, transfers, rentals, spa and packages share one catalog and one booking flow.
 * Pricing behaviour is data (pricing_model + options), so each operator configures its own rules:
 *   options.child_percent   child price as % of adult (default 100)
 *   options.tiers           [{min_pax, price}] group-size price breaks for per_person
 *   options.components      [{catalog_item_id, quantity}] for packages
 *   options.max_pax         hard cap per booking
 */
class TourismService
{
    public const KINDS = ['tour', 'activity', 'transfer', 'rental', 'package', 'spa', 'event', 'service', 'other'];

    public function __construct(private FolioService $folios) {}

    /** @return array{unit_price:int, quantity:float, line_total:int} */
    public function price(CatalogItem $item, int $adults, int $children, float $units = 1, ?int $override = null): array
    {
        $base = $override ?? $item->price;
        $o = $item->options ?? [];
        $pax = $adults + $children;
        if (! empty($o['max_pax']) && $pax > $o['max_pax']) {
            throw new ApiError('validation_failed', "Maximum {$o['max_pax']} people for this service.", 422);
        }
        switch ($item->pricing_model) {
            case 'per_person':
                if (! empty($o['tiers']) && $override === null) {
                    $tiers = collect($o['tiers'])->sortByDesc('min_pax');
                    $base = (int) ($tiers->first(fn ($t) => $pax >= $t['min_pax'])['price'] ?? $base);
                }
                $childPct = (int) ($o['child_percent'] ?? 100);
                $total = $base * $adults + (int) round($base * $childPct / 100) * $children;
                $qty = $pax;
                $unit = (int) round($total / max($pax, 1));
                break;
            case 'per_day':
            case 'per_night':
            case 'per_hour':
                $qty = $units;
                $unit = $base;
                $total = (int) round($base * $units);
                break;
            default: // flat, per_group
                $qty = 1;
                $unit = $base;
                $total = $base;
        }

        return ['unit_price' => $unit, 'quantity' => $qty, 'line_total' => $total];
    }

    /** Expand an item (incl. package components) plus optional add-ons into priced lines. */
    public function buildLines(CatalogItem $item, array $d, ?int $override): array
    {
        $adults = (int) ($d['pax_adults'] ?? 1);
        $children = (int) ($d['pax_children'] ?? 0);
        $units = (float) ($d['units'] ?? 1);
        $lines = [];
        if ($item->kind === 'package' && ! empty($item->options['components'])) {
            foreach ($item->options['components'] as $c) {
                $comp = CatalogItem::findOrFail($c['catalog_item_id']);
                $p = $this->price($comp, $adults, $children, (float) ($c['quantity'] ?? 1));
                $lines[] = ['catalog_item_id' => $comp->id, 'description' => $comp->name, 'rates' => $comp->tax_rate_ids] + $p;
            }
            if ($item->price > 0) { // package-level supplement
                $lines[] = ['catalog_item_id' => $item->id, 'description' => $item->name, 'rates' => $item->tax_rate_ids] + $this->price($item, $adults, $children, $units, $override);
            }
        } else {
            $lines[] = ['catalog_item_id' => $item->id, 'description' => $item->name, 'rates' => $item->tax_rate_ids] + $this->price($item, $adults, $children, $units, $override);
        }
        foreach ($d['addons'] ?? [] as $a) {
            $add = CatalogItem::where('is_active', true)->findOrFail($a['catalog_item_id']);
            $q = (float) ($a['quantity'] ?? 1);
            $lines[] = ['catalog_item_id' => $add->id, 'description' => $add->name, 'rates' => $add->tax_rate_ids, 'unit_price' => $add->price, 'quantity' => $q, 'line_total' => (int) round($add->price * $q)];
        }

        return $lines;
    }

    public function totals(array $lines): array
    {
        $net = 0;
        $tax = 0;
        $taxLines = [];
        foreach ($lines as $l) {
            $rates = $l['rates'] !== null ? Taxes::activeRates($l['rates']) : Taxes::activeRates();
            $c = Taxes::compute($l['line_total'], $rates);
            $net += $c['net'];
            $tax += $c['tax_total'];
            foreach ($c['taxes'] as $t) {
                $taxLines[$t['name']] = ($taxLines[$t['name']] ?? 0) + $t['amount'];
            }
        }

        return ['net' => $net, 'tax_total' => $tax, 'total' => $net + $tax, 'tax_lines' => $taxLines];
    }

    public function quote(array $d): array
    {
        $item = CatalogItem::where('is_active', true)->findOrFail($d['catalog_item_id']);
        $override = null;
        if (! empty($d['departure_id'])) {
            $override = TourDeparture::findOrFail($d['departure_id'])->price_override;
        }
        $lines = $this->buildLines($item, $d, $override);

        return ['lines' => $lines] + $this->totals($lines);
    }

    public function departureStats(TourDeparture $dep): array
    {
        $booked = (int) Booking::where('departure_id', $dep->id)->whereNotIn('status', ['cancelled', 'no_show'])->sum(DB::raw('pax_adults + pax_children'));

        return ['booked' => $booked, 'available' => max($dep->capacity - $booked, 0)];
    }

    public function create(array $d): Booking
    {
        return DB::transaction(function () use ($d) {
            $item = CatalogItem::where('is_active', true)->findOrFail($d['catalog_item_id']);
            if (! in_array($item->kind, self::KINDS, true) || in_array($item->kind, ['menu', 'drink', 'product'], true)) {
                throw new ApiError('validation_failed', 'This catalog item is not a bookable service.', 422);
            }
            $pax = ($d['pax_adults'] ?? 1) + ($d['pax_children'] ?? 0);
            $dep = null;
            $date = $d['date'] ?? null;
            if (! empty($d['departure_id'])) {
                $dep = TourDeparture::where('id', $d['departure_id'])->lockForUpdate()->firstOrFail();
                if ($dep->catalog_item_id !== $item->id) {
                    throw new ApiError('validation_failed', 'Departure belongs to a different tour.', 422);
                }
                if ($dep->status !== 'open') {
                    throw new ApiError('departure_closed', 'This departure is not open for booking.', 409);
                }
                if ($this->departureStats($dep)['available'] < $pax) {
                    throw new ApiError('no_availability', 'Not enough seats left on this departure.', 409, ['available' => $this->departureStats($dep)['available']]);
                }
                $date = $dep->date->toDateString();
            }
            if (! $date) {
                throw new ApiError('validation_failed', 'A date or departure is required.', 422);
            }
            $lines = $this->buildLines($item, $d, $dep?->price_override);
            $t = $this->totals($lines);
            $b = new Booking;
            $b->forceFill([
                'id' => $d['id'] ?? (string) Str::uuid7(), 'property_id' => $d['property_id'] ?? $item->property_id, 'number' => $d['number'] ?? Numbering::next('BKG', Booking::class),
                'guest_id' => $d['guest_id'] ?? null, 'customer_name' => $d['customer_name'], 'customer_phone' => $d['customer_phone'] ?? null, 'customer_email' => $d['customer_email'] ?? null,
                'catalog_item_id' => $item->id, 'departure_id' => $dep?->id, 'date' => $date, 'start_time' => $d['start_time'] ?? $dep?->start_time,
                'pax_adults' => $d['pax_adults'] ?? 1, 'pax_children' => $d['pax_children'] ?? 0, 'status' => $d['status'] ?? 'confirmed', 'source' => $d['source'] ?? 'walk_in',
                'total' => $t['total'], 'deposit' => 0, 'pickup_location' => $d['pickup_location'] ?? null, 'notes' => $d['notes'] ?? null, 'meta' => $d['meta'] ?? null,
                'reservation_id' => $d['reservation_id'] ?? null,
            ])->save();
            foreach ($lines as $l) {
                $bl = new BookingLine;
                $bl->forceFill(['property_id' => $b->property_id, 'booking_id' => $b->id, 'catalog_item_id' => $l['catalog_item_id'], 'description' => $l['description'], 'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'], 'line_total' => $l['line_total']])->save();
            }
            foreach ($d['participants'] ?? [] as $p) {
                $bp = new BookingParticipant;
                $bp->forceFill($p + ['property_id' => $b->property_id, 'booking_id' => $b->id])->save();
            }
            $this->postCharges($b, $t, $d['charge_to_reservation_id'] ?? null);
            Audit::log('booking.created', 'booking', $b->id, null, ['number' => $b->number, 'total' => $b->total]);

            return $b->fresh();
        });
    }

    /** Charges go to the booking's own folio, or onto a checked-in hotel guest's room folio. */
    private function postCharges(Booking $b, array $t, ?string $reservationId): void
    {
        if ($reservationId) {
            $res = Reservation::findOrFail($reservationId);
            if ($res->status !== 'checked_in') {
                throw new ApiError('invalid_state', 'Guest is not checked in.', 409);
            }
            $folio = Folio::where('reservation_id', $res->id)->firstOrFail();
            $b->forceFill(['reservation_id' => $res->id])->save();
        } else {
            $folio = new Folio;
            $folio->forceFill(['property_id' => $b->property_id, 'number' => Numbering::next('FOL', Folio::class), 'booking_id' => $b->id, 'guest_id' => $b->guest_id, 'label' => 'Booking '.$b->number, 'status' => 'open'])->save();
        }
        $this->folios->post($folio, 'charge', 'Booking '.$b->number, $t['net'], 1, ['idempotency_key' => 'booking:'.$b->id, 'source_type' => 'booking', 'source_id' => $b->id]);
        foreach ($t['tax_lines'] as $name => $amt) {
            if ($amt > 0) {
                $this->folios->post($folio, 'tax', $name.' '.$b->number, $amt, 1, ['idempotency_key' => 'booking:'.$b->id.':'.Str::slug($name), 'source_type' => 'booking', 'source_id' => $b->id]);
            }
        }
    }

    public function folioFor(Booking $b): ?Folio
    {
        return $b->reservation_id
            ? Folio::where('reservation_id', $b->reservation_id)->first()
            : Folio::where('booking_id', $b->id)->first();
    }

    /** Amount of this booking still unpaid (booking-source entries minus payments tagged to it). */
    public function balance(Booking $b): int
    {
        $folio = $this->folioFor($b);
        if (! $folio) {
            return 0;
        }
        if ($b->reservation_id) { // on a room folio: only this booking's charges and its own payments
            $charged = (int) FolioEntry::where('folio_id', $folio->id)->whereNull('voided_at')->where('source_type', 'booking')->where('source_id', $b->id)->sum('amount');
            $paid = (int) -FolioEntry::where('folio_id', $folio->id)->whereNull('voided_at')->where('amount', '<', 0)->where('meta->booking_id', $b->id)->sum('amount');

            return $charged - $paid;
        }

        return $this->folios->balance($folio);
    }

    public function refreshPaidStatus(Booking $b): Booking
    {
        $paid = (int) Payment::where('booking_id', $b->id)->where('status', 'successful')->sum('amount');
        $b->forceFill(['deposit' => $paid]);
        if ($b->status === 'confirmed' && $b->total > 0 && $this->balance($b) <= 0 && ! $b->reservation_id) {
            $b->status = 'paid';
        }
        $b->save();

        return $b;
    }

    public function transition(Booking $b, string $to): Booking
    {
        $allowed = [
            'inquiry' => ['confirmed', 'cancelled'],
            'confirmed' => ['paid', 'in_progress', 'cancelled', 'no_show'],
            'paid' => ['in_progress', 'cancelled', 'no_show'],
            'in_progress' => ['completed'],
        ];
        if (! in_array($to, $allowed[$b->status] ?? [], true)) {
            throw new ApiError('invalid_state', "Cannot move a {$b->status} booking to $to.", 409);
        }
        if ($to === 'in_progress' && ! BookingAssignment::where('booking_id', $b->id)->exists() && $this->requiresAssignment($b)) {
            throw new ApiError('assignment_required', 'Assign a guide/vehicle before dispatching.', 422);
        }
        $b->forceFill(['status' => $to])->save();
        Audit::log("booking.$to", 'booking', $b->id);

        return $b;
    }

    private function requiresAssignment(Booking $b): bool
    {
        return (bool) (CatalogItem::find($b->catalog_item_id)?->options['requires_assignment'] ?? false);
    }

    public function assign(Booking $b, string $resourceId, ?string $role): BookingAssignment
    {
        $res = TourismResource::where('is_active', true)->findOrFail($resourceId);
        $clash = BookingAssignment::where('resource_id', $res->id)->where('date', $b->date->toDateString())->where('booking_id', '!=', $b->id)
            ->whereIn('booking_id', Booking::whereNotIn('status', ['cancelled', 'no_show', 'completed'])->select('id'))
            // several bookings may share one resource only when they share the same departure
            ->whereNotIn('booking_id', Booking::where('departure_id', $b->departure_id)->whereNotNull('departure_id')->select('id'))->exists();
        if ($clash) {
            throw new ApiError('resource_busy', "{$res->name} is already assigned on this date.", 409);
        }
        $a = BookingAssignment::firstOrNew(['booking_id' => $b->id, 'resource_id' => $res->id]);
        $a->forceFill(['property_id' => $b->property_id, 'role' => $role ?? $res->kind, 'date' => $b->date->toDateString(), 'tenant_id' => app(Tenancy::class)->id()])->save();
        Audit::log('booking.resource_assigned', 'booking', $b->id, null, ['resource' => $res->name]);

        return $a;
    }

    public function manifest(TourDeparture $dep): array
    {
        $bookings = Booking::where('departure_id', $dep->id)->whereNotIn('status', ['cancelled', 'no_show'])->get();
        $ids = $bookings->pluck('id');

        return [
            'departure' => $dep->toArray() + $this->departureStats($dep),
            'tour' => CatalogItem::find($dep->catalog_item_id)?->only(['id', 'name']),
            'bookings' => $bookings->map(fn ($b) => $b->toArray() + ['pax' => $b->pax_adults + $b->pax_children, 'balance' => $this->balance($b)])->values(),
            'participants' => BookingParticipant::whereIn('booking_id', $ids)->get(),
            'resources' => BookingAssignment::whereIn('booking_id', $ids)->get()->unique('resource_id')->map(fn ($a) => TourismResource::find($a->resource_id))->filter()->values(),
        ];
    }
}
