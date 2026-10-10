<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class RoomsTest extends TestCase
{
    private function setup1(array $extra = []): array
    {
        [$h] = $this->registerCompany('Kivu Lodge', null, $extra);
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Deluxe', 'base_price' => 100000], $h)->json('data');
        $rooms = [];
        foreach (['101', '102'] as $n) {
            $rooms[] = $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => $n], $h)->json('data');
        }

        return [$h, $type, $rooms];
    }

    public function test_availability_quote_and_overbooking_prevention(): void
    {
        [$h, $type] = $this->setup1();
        $this->getJson("/api/v1/availability?room_type_id={$type['id']}&check_in=2027-01-10&check_out=2027-01-12", $h)
            ->assertOk()->assertJsonPath('data.free', 2)->assertJsonPath('data.nights', 2)->assertJsonPath('data.subtotal', 200000);
        $book = fn () => $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'check_in' => '2027-01-10', 'check_out' => '2027-01-12', 'guest' => ['name' => 'Aline']], $h);
        $book()->assertCreated();
        $book()->assertCreated();
        $book()->assertStatus(409)->assertJsonPath('error.code', 'no_availability');
        // adjacent dates are fine (checkout day is free)
        $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'check_in' => '2027-01-12', 'check_out' => '2027-01-13', 'guest' => ['name' => 'Bob']], $h)->assertCreated();
    }

    public function test_seasonal_rate_plan_and_vat_inclusive_quote(): void
    {
        [$h, $type] = $this->setup1();
        $this->postJson('/api/v1/rate-plans', ['name' => 'Weekend', 'price' => 150000, 'days_of_week' => [5, 6], 'priority' => 5], $h)->assertCreated();
        // 2027-01-08 is Friday, 09 Saturday, 10 Sunday
        $q = $this->getJson("/api/v1/availability?room_type_id={$type['id']}&check_in=2027-01-08&check_out=2027-01-11", $h)->json('data');
        $this->assertSame(150000 + 150000 + 100000, $q['subtotal']);
        $this->assertSame(0, $q['tax_total'] - array_sum(array_column($q['taxes'], 'amount')));
        $this->assertGreaterThan(0, $q['tax_total']); // 18% VAT is on by default
    }

    public function test_full_stay_lifecycle_with_folio_night_audit_and_checkout(): void
    {
        [$h, $type, $rooms] = $this->setup1(['modules' => ['rooms', 'pos', 'housekeeping']]);
        $today = now()->toDateString();
        $res = $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'room_id' => $rooms[0]['id'], 'check_in' => $today, 'check_out' => now()->addDays(2)->toDateString(),
            'guest' => ['name' => 'Aline']], $h)->assertCreated()->json('data');
        // check-in needs ID (default setting)
        $this->postJson("/api/v1/reservations/{$res['id']}/check-in", [], $h)->assertStatus(422)->assertJsonPath('error.code', 'id_required');
        $this->postJson("/api/v1/reservations/{$res['id']}/check-in", ['guest' => ['id_number' => '1199']], $h)->assertOk()->assertJsonPath('data.status', 'checked_in');

        $this->postJson('/api/v1/night-audit', [], $h)->assertOk()->assertJsonPath('data.posted', 1);
        $this->postJson('/api/v1/night-audit', [], $h)->assertOk()->assertJsonPath('data.posted', 0); // idempotent
        $folio = $this->getJson("/api/v1/reservations/{$res['id']}", $h)->json('folio');
        $this->assertSame(118000, $folio['balance']); // 100000 + 18% VAT

        $this->postJson("/api/v1/reservations/{$res['id']}/check-out", [], $h)->assertStatus(409)->assertJsonPath('error.code', 'balance_due');
        $this->postJson("/api/v1/folios/{$folio['id']}/payments", ['amount' => 118000, 'method_type' => 'cash'], $h)->assertCreated()->assertJsonPath('balance', 0);
        $this->postJson("/api/v1/reservations/{$res['id']}/check-out", [], $h)->assertOk()->assertJsonPath('data.status', 'checked_out');

        $board = $this->getJson('/api/v1/room-board', $h)->json('data');
        $this->assertSame('dirty', collect($board)->firstWhere('number', '101')['housekeeping_status']);
        $tasks = $this->getJson('/api/v1/tasks?filter[type]=clean', $h)->json('data');
        $this->assertCount(1, $tasks);
        // closed folio is immutable
        $this->postJson("/api/v1/folios/{$folio['id']}/entries", ['type' => 'charge', 'description' => 'x', 'amount' => 5], $h)->assertStatus(409);
    }

    public function test_same_room_cannot_be_double_assigned(): void
    {
        [$h, $type, $rooms] = $this->setup1();
        $mk = fn ($n) => $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'check_in' => '2027-03-01', 'check_out' => '2027-03-03', 'guest' => ['name' => $n]], $h)->json('data');
        $a = $mk('A');
        $b = $mk('B');
        $this->postJson("/api/v1/reservations/{$a['id']}/assign", ['room_id' => $rooms[0]['id']], $h)->assertOk();
        $this->postJson("/api/v1/reservations/{$b['id']}/assign", ['room_id' => $rooms[0]['id']], $h)->assertStatus(409)->assertJsonPath('error.code', 'room_unavailable');
        $this->postJson("/api/v1/reservations/{$b['id']}/assign", ['room_id' => $rooms[1]['id']], $h)->assertOk();
    }

    public function test_cancel_frees_inventory_and_idempotent_replay(): void
    {
        [$h, $type] = $this->setup1();
        $id = (string) Str::uuid7();
        $payload = ['id' => $id, 'room_type_id' => $type['id'], 'check_in' => '2027-05-01', 'check_out' => '2027-05-02', 'guest' => ['name' => 'A']];
        $this->postJson('/api/v1/reservations', $payload, $h)->assertCreated();
        $this->postJson('/api/v1/reservations', $payload, $h)->assertOk(); // replay, no duplicate
        $this->assertSame(1, $this->getJson('/api/v1/reservations', $h)->json('meta.total'));
        $this->postJson("/api/v1/reservations/$id/cancel", ['reason' => 'changed plans'], $h)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->getJson("/api/v1/availability?room_type_id={$type['id']}&check_in=2027-05-01&check_out=2027-05-02", $h)->assertJsonPath('data.free', 2);
    }

    public function test_folio_entries_can_be_voided_not_deleted(): void
    {
        [$h, $type, $rooms] = $this->setup1();
        $res = $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'check_in' => '2027-06-01', 'check_out' => '2027-06-02', 'guest' => ['name' => 'A']], $h)->json('data');
        $folio = $this->getJson("/api/v1/reservations/{$res['id']}", $h)->json('folio');
        $e = $this->postJson("/api/v1/folios/{$folio['id']}/entries", ['type' => 'charge', 'description' => 'Laundry', 'amount' => 5000], $h)->assertCreated()->json('data');
        $this->postJson("/api/v1/folio-entries/{$e['id']}/void", [], $h)->assertStatus(422);
        $this->postJson("/api/v1/folio-entries/{$e['id']}/void", ['reason' => 'entered twice'], $h)->assertOk()->assertJsonPath('balance', 0);
        $this->postJson("/api/v1/folio-entries/{$e['id']}/void", ['reason' => 'again'], $h)->assertStatus(409);
    }
}
