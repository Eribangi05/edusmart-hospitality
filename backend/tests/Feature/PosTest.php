<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class PosTest extends TestCase
{
    private function menu(array $h): array
    {
        $beer = $this->postJson('/api/v1/catalog-items', ['kind' => 'drink', 'name' => 'Primus', 'price' => 1500, 'station' => 'bar'], $h)->assertCreated()->json('data');
        $brochette = $this->postJson('/api/v1/catalog-items', ['kind' => 'menu', 'name' => 'Brochette', 'price' => 3000, 'station' => 'kitchen'], $h)->assertCreated()->json('data');

        return [$beer, $brochette];
    }

    public function test_order_totals_are_computed_by_the_server_with_inclusive_vat(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer, $br] = $this->menu($h);
        // VAT is exclusive by default => price + 18%
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id'], 'quantity' => 2], ['catalog_item_id' => $br['id']]]], $h)->assertCreated()->json('data');
        $this->assertSame(6000, $o['subtotal']);
        $this->assertSame(1080, $o['tax']);
        $this->assertSame(7080, $o['total']);
        // make VAT inclusive: listed price already contains tax
        $vat = collect($this->getJson('/api/v1/tax-rates', $h)->json('data'))->firstWhere('key', 'vat');
        $this->patchJson("/api/v1/tax-rates/{$vat['id']}", ['inclusive' => true], $h)->assertOk();
        $o2 = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $br['id']]]], $h)->json('data');
        $this->assertSame(3000, $o2['total']);
        $this->assertSame(458, $o2['tax']); // 3000 - 3000/1.18
    }

    public function test_client_cannot_override_price_without_permission(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer] = $this->menu($h);
        $tenantId = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $waiter = $this->userWithRole($tenantId, 'waiter', 'w@example.com');
        $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id'], 'unit_price' => 1]]], $waiter)->assertStatus(403);
        $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id'], 'unit_price' => 1]]], $h)->assertCreated()->assertJsonPath('data.subtotal', 1);
    }

    public function test_cash_payment_closes_order_and_idempotent_replay(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer] = $this->menu($h);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']]]], $h)->json('data');
        $body = ['amount' => $o['total'], 'method_type' => 'cash', 'idempotency_key' => 'k1'];
        $this->postJson("/api/v1/orders/{$o['id']}/pay", $body, $h)->assertCreated()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/v1/orders/{$o['id']}/pay", $body, $h)->assertStatus(409); // order already paid
        $this->postJson("/api/v1/orders/{$o['id']}/items", ['items' => [['catalog_item_id' => $beer['id']]]], $h)->assertStatus(409);
    }

    public function test_partial_payments_and_momo_pending_then_confirmed(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer, $br] = $this->menu($h);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $br['id'], 'quantity' => 2]]], $h)->json('data'); // 7080
        $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => 3000, 'method_type' => 'cash'], $h)->assertCreated()->assertJsonPath('data.status', 'open');
        $mo = $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => 4080, 'method_type' => 'momo', 'payer_phone' => '250788123456'], $h)->assertCreated();
        $this->assertSame('pending', $mo->json('payment.status'));
        $this->assertSame('open', $mo->json('data.status'));
        $pid = $mo->json('payment.id');
        $this->getJson("/api/v1/payments/$pid", $h)->assertJsonPath('data.status', 'successful');
        $this->assertSame('paid', $this->getJson("/api/v1/orders/{$o['id']}", $h)->json('data.status'));
    }

    public function test_momo_failure_leaves_order_open(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer] = $this->menu($h);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']]]], $h)->json('data');
        $p = $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => $o['total'], 'method_type' => 'momo', 'payer_phone' => '250780000000'], $h)->json('payment');
        $this->getJson("/api/v1/payments/{$p['id']}", $h)->assertJsonPath('data.status', 'failed');
        $this->assertSame('open', $this->getJson("/api/v1/orders/{$o['id']}", $h)->json('data.status'));
    }

    public function test_void_after_send_requires_manager_pin(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer] = $this->menu($h);
        $tenantId = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $roles = $this->getJson('/api/v1/roles', $h)->json('data');
        $mgr = collect($roles)->firstWhere('key', 'manager');
        $this->postJson('/api/v1/users', ['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'long-enough-pass', 'pin' => '4321', 'roles' => [$mgr['id']]], $h)->assertCreated();
        $waiter = $this->userWithRole($tenantId, 'waiter', 'w@example.com');

        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']]]], $waiter)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/send", [], $waiter)->assertOk();
        $line = $this->getJson("/api/v1/orders/{$o['id']}", $waiter)->json('data.items.0');
        $this->postJson("/api/v1/order-items/{$line['id']}/void", ['reason' => 'wrong item'], $waiter)->assertStatus(403)->assertJsonPath('error.code', 'manager_required');
        $this->postJson("/api/v1/order-items/{$line['id']}/void", ['reason' => 'wrong item', 'manager_pin' => '0000'], $waiter)->assertStatus(403);
        $this->postJson("/api/v1/order-items/{$line['id']}/void", ['reason' => 'wrong item', 'manager_pin' => '4321'], $waiter)->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson("/api/v1/orders/{$o['id']}/discount", ['percent_bp' => 1000], $waiter)->assertStatus(403);
    }

    public function test_split_and_merge(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer, $br] = $this->menu($h);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']], ['catalog_item_id' => $br['id']]]], $h)->json('data');
        $beerLine = collect($o['items'])->firstWhere('name', 'Primus');
        $s = $this->postJson("/api/v1/orders/{$o['id']}/split", ['item_ids' => [$beerLine['id']]], $h)->assertCreated();
        $this->assertSame(1770, $s->json('data.total'));
        $this->assertSame(3540, $s->json('original.total'));
        $m = $this->postJson("/api/v1/orders/{$o['id']}/merge", ['from_order_id' => $s->json('data.id')], $h)->assertOk();
        $this->assertSame(5310, $m->json('data.total'));
    }

    public function test_discount_applies_before_tax(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [, $br] = $this->menu($h);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $br['id'], 'quantity' => 2]]], $h)->json('data');
        $d = $this->postJson("/api/v1/orders/{$o['id']}/discount", ['percent_bp' => 1000], $h)->assertOk()->json('data');
        $this->assertSame(600, $d['discount']);
        $this->assertSame(5400, $d['subtotal']);
        $this->assertSame(972, $d['tax']);
        $this->assertSame(6372, $d['total']);
    }

    public function test_charge_to_room_posts_to_folio(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer] = $this->menu($h);
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Std', 'base_price' => 50000], $h)->json('data');
        $room = $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '1'], $h)->json('data');
        $res = $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'room_id' => $room['id'], 'check_in' => now()->toDateString(), 'check_out' => now()->addDay()->toDateString(), 'guest' => ['name' => 'A', 'id_number' => '1']], $h)->json('data');
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/charge-room", ['reservation_id' => $res['id']], $h)->assertStatus(409); // not checked in yet
        $this->postJson("/api/v1/reservations/{$res['id']}/check-in", [], $h)->assertOk();
        $this->postJson("/api/v1/orders/{$o['id']}/charge-room", ['reservation_id' => $res['id']], $h)->assertOk()->assertJsonPath('data.status', 'paid');
        $folio = $this->getJson("/api/v1/reservations/{$res['id']}", $h)->json('folio');
        $this->assertSame($o['total'], $folio['balance']);
    }

    public function test_shift_cash_up_reports_variance(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer] = $this->menu($h);
        $s = $this->postJson('/api/v1/shifts', ['opening_float' => 10000], $h)->assertCreated()->json('data');
        $this->postJson('/api/v1/shifts', ['opening_float' => 1], $h)->assertStatus(409);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => $o['total'], 'method_type' => 'cash'], $h)->assertCreated();
        $closed = $this->postJson("/api/v1/shifts/{$s['id']}/close", ['counted_cash' => 11700], $h)->assertOk()->json('data');
        $this->assertSame(11770, $closed['expected_cash']);
        $this->assertSame(-70, $closed['variance']);
    }

    public function test_kitchen_tickets_route_by_station(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer, $br] = $this->menu($h);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']], ['catalog_item_id' => $br['id']]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/send", [], $h)->assertOk();
        $k = $this->getJson('/api/v1/kitchen/tickets?station=kitchen', $h)->json('data');
        $this->assertCount(1, $k);
        $this->assertSame('Brochette', $k[0]['items'][0]['name']);
        $this->postJson('/api/v1/order-items/'.$k[0]['items'][0]['id'].'/status', ['status' => 'ready'], $h)->assertOk();
        $this->assertCount(1, $this->getJson('/api/v1/kitchen/tickets?station=bar', $h)->json('data'));
    }

    public function test_offline_order_replay_is_idempotent(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$beer] = $this->menu($h);
        $id = (string) Str::uuid7();
        $p = ['id' => $id, 'number' => 'D3-0042', 'items' => [['catalog_item_id' => $beer['id']]]];
        $this->postJson('/api/v1/orders', $p, $h)->assertCreated();
        $this->postJson('/api/v1/orders', $p, $h)->assertOk();
        $this->assertSame(1, $this->getJson('/api/v1/orders', $h)->json('meta.total'));
    }
}
