<?php

namespace Tests\Feature;

use Tests\TestCase;

class TourismTest extends TestCase
{
    private function company(): array
    {
        [$h] = $this->registerCompany('Volcano Treks', null, ['modules' => ['tourism', 'rooms', 'pos']]);
        // turn VAT off so numbers are easy; one test re-enables it
        $vat = collect($this->getJson('/api/v1/tax-rates', $h)->json('data'))->firstWhere('key', 'vat');
        $this->patchJson("/api/v1/tax-rates/{$vat['id']}", ['rate_bp' => 0], $h);

        return [$h, $vat];
    }

    private function item(array $h, array $over = []): array
    {
        return $this->postJson('/api/v1/catalog-items', array_merge(['kind' => 'tour', 'name' => 'Gorilla trek', 'price' => 100000, 'pricing_model' => 'per_person',
            'options' => ['child_percent' => 50, 'tiers' => [['min_pax' => 4, 'price' => 90000]]]], $over), $h)->assertCreated()->json('data');
    }

    public function test_per_person_pricing_with_children_and_group_tiers(): void
    {
        [$h] = $this->company();
        $tour = $this->item($h);
        $q = $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $tour['id'], 'pax_adults' => 2, 'pax_children' => 1], $h)->assertOk()->json('data');
        $this->assertSame(250000, $q['total']);         // 2*100k + 50k
        $q = $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $tour['id'], 'pax_adults' => 4], $h)->json('data');
        $this->assertSame(360000, $q['total']);         // tier price 90k * 4
    }

    public function test_other_pricing_models_and_packages_and_addons(): void
    {
        [$h] = $this->company();
        $car = $this->item($h, ['kind' => 'rental', 'name' => '4x4 hire', 'price' => 80000, 'pricing_model' => 'per_day', 'options' => null]);
        $transfer = $this->item($h, ['kind' => 'transfer', 'name' => 'Airport transfer', 'price' => 30000, 'pricing_model' => 'per_group', 'options' => null]);
        $this->assertSame(240000, $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $car['id'], 'units' => 3], $h)->json('data.total'));
        $this->assertSame(30000, $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $transfer['id'], 'pax_adults' => 5], $h)->json('data.total'));
        $pkg = $this->item($h, ['kind' => 'package', 'name' => 'Weekend safari', 'price' => 0, 'pricing_model' => 'flat',
            'options' => ['components' => [['catalog_item_id' => $car['id'], 'quantity' => 2], ['catalog_item_id' => $transfer['id'], 'quantity' => 1]]]]);
        $q = $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $pkg['id'], 'addons' => [['catalog_item_id' => $transfer['id'], 'quantity' => 2]]], $h)->json('data');
        $this->assertSame(160000 + 30000 + 60000, $q['total']);
        $this->assertCount(3, $q['lines']);
        $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $this->item($h, ['options' => ['max_pax' => 8]])['id'], 'pax_adults' => 9], $h)->assertStatus(422);
    }

    public function test_departure_capacity_booking_payment_and_manifest(): void
    {
        [$h] = $this->company();
        $tour = $this->item($h);
        $dep = $this->postJson('/api/v1/tour-departures', ['catalog_item_id' => $tour['id'], 'date' => '2027-02-10', 'start_time' => '06:00', 'capacity' => 6], $h)->assertCreated()->json('data');
        $b1 = $this->postJson('/api/v1/bookings', ['catalog_item_id' => $tour['id'], 'departure_id' => $dep['id'], 'customer_name' => 'Emma', 'pax_adults' => 4,
            'participants' => [['name' => 'Emma', 'nationality' => 'UK', 'id_number' => 'P1']]], $h)->assertCreated()->json('data');
        $this->assertSame(360000, $b1['total']);
        $this->assertSame(360000, $b1['balance']);
        $this->postJson('/api/v1/bookings', ['catalog_item_id' => $tour['id'], 'departure_id' => $dep['id'], 'customer_name' => 'Too many', 'pax_adults' => 3], $h)
            ->assertStatus(409)->assertJsonPath('error.details.available', 2);
        $this->postJson("/api/v1/bookings/{$b1['id']}/payments", ['amount' => 100000, 'method_type' => 'cash'], $h)->assertCreated()->assertJsonPath('data.balance', 260000)->assertJsonPath('data.status', 'confirmed');
        $this->postJson("/api/v1/bookings/{$b1['id']}/payments", ['amount' => 999999999, 'method_type' => 'cash'], $h)->assertStatus(422);
        $this->postJson("/api/v1/bookings/{$b1['id']}/payments", ['amount' => 260000, 'method_type' => 'cash'], $h)->assertCreated()->assertJsonPath('data.status', 'paid');
        $deps = $this->getJson('/api/v1/tourism/departures', $h)->json('data');
        $this->assertSame(2, $deps[0]['available']);
        $m = $this->getJson("/api/v1/tourism/departures/{$dep['id']}/manifest", $h)->json('data');
        $this->assertCount(1, $m['bookings']);
        $this->assertSame(4, $m['bookings'][0]['pax']);
        $this->assertSame('Emma', $m['participants'][0]['name']);
        // cancelling frees seats
        $this->postJson("/api/v1/bookings/{$b1['id']}/status/cancelled", [], $h)->assertOk();
        $this->assertSame(6, $this->getJson('/api/v1/tourism/departures', $h)->json('data.0.available'));
    }

    public function test_guide_and_vehicle_assignment_conflicts_and_dispatch_rule(): void
    {
        [$h] = $this->company();
        $tour = $this->item($h, ['options' => ['requires_assignment' => true]]);
        $guide = $this->postJson('/api/v1/tourism-resources', ['kind' => 'guide', 'name' => 'Jean'], $h)->json('data');
        $mk = fn ($n) => $this->postJson('/api/v1/bookings', ['catalog_item_id' => $tour['id'], 'date' => '2027-04-01', 'customer_name' => $n], $h)->assertCreated()->json('data');
        [$a, $b] = [$mk('A'), $mk('B')];
        $this->postJson("/api/v1/bookings/{$a['id']}/status/in_progress", [], $h)->assertStatus(422)->assertJsonPath('error.code', 'assignment_required');
        $this->postJson("/api/v1/bookings/{$a['id']}/assign", ['resource_id' => $guide['id']], $h)->assertOk();
        $this->postJson("/api/v1/bookings/{$b['id']}/assign", ['resource_id' => $guide['id']], $h)->assertStatus(409)->assertJsonPath('error.code', 'resource_busy');
        $this->postJson("/api/v1/bookings/{$a['id']}/status/in_progress", [], $h)->assertOk();
        $this->postJson("/api/v1/bookings/{$a['id']}/status/completed", [], $h)->assertOk();
        $this->postJson("/api/v1/bookings/{$a['id']}/status/confirmed", [], $h)->assertStatus(409);
    }

    public function test_vat_applies_and_tour_can_be_charged_to_hotel_room_folio(): void
    {
        [$h, $vat] = $this->company();
        $this->patchJson("/api/v1/tax-rates/{$vat['id']}", ['rate_bp' => 1800], $h);
        $tour = $this->item($h, ['price' => 50000, 'pricing_model' => 'flat', 'options' => null]);
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Std', 'base_price' => 50000], $h)->json('data');
        $room = $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '1'], $h)->json('data');
        $res = $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'room_id' => $room['id'], 'check_in' => now()->toDateString(), 'check_out' => now()->addDay()->toDateString(), 'guest' => ['name' => 'Emma', 'id_number' => 'X']], $h)->json('data');
        $this->postJson('/api/v1/bookings', ['catalog_item_id' => $tour['id'], 'date' => now()->toDateString(), 'customer_name' => 'Emma', 'charge_to_reservation_id' => $res['id']], $h)->assertStatus(409);
        $this->postJson("/api/v1/reservations/{$res['id']}/check-in", [], $h)->assertOk();
        $b = $this->postJson('/api/v1/bookings', ['catalog_item_id' => $tour['id'], 'date' => now()->toDateString(), 'customer_name' => 'Emma', 'charge_to_reservation_id' => $res['id']], $h)->assertCreated()->json('data');
        $this->assertSame(59000, $b['total']);
        $this->assertSame(59000, $this->getJson("/api/v1/reservations/{$res['id']}", $h)->json('folio.balance'));
    }

    public function test_tourism_is_isolated_and_gated(): void
    {
        [$h] = $this->company();
        [$other] = $this->registerCompany('Hotel Only');
        $this->getJson('/api/v1/bookings', $other)->assertStatus(403)->assertJsonPath('error.code', 'module_disabled');
        $tour = $this->item($h);
        $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $tour['id']], $other)->assertStatus(403);
        $this->putJson('/api/v1/modules/tourism', ['enabled' => true], $other)->assertOk();
        $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $tour['id']], $other)->assertStatus(422);
    }

    public function test_meals_cannot_be_booked_as_tours(): void
    {
        [$h] = $this->company();
        $beer = $this->postJson('/api/v1/catalog-items', ['kind' => 'drink', 'name' => 'Beer', 'price' => 1000], $h)->json('data');
        $this->postJson('/api/v1/bookings', ['catalog_item_id' => $beer['id'], 'date' => '2027-01-01', 'customer_name' => 'X'], $h)->assertStatus(422);
    }
}
