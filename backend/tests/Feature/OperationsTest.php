<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationsTest extends TestCase
{
    private function co(): array
    {
        [$h] = $this->registerCompany('Kivu Lodge', null, ['modules' => ['rooms', 'pos', 'inventory', 'staff', 'housekeeping', 'finance', 'tourism']]);
        $vat = collect($this->getJson('/api/v1/tax-rates', $h)->json('data'))->firstWhere('key', 'vat');
        $this->patchJson("/api/v1/tax-rates/{$vat['id']}", ['rate_bp' => 0], $h);

        return [$h, $this->getJson('/api/v1/me/context', $h)->json('tenant.id')];
    }

    public function test_recipe_deducts_ingredients_on_payment_and_ledger_is_append_only(): void
    {
        [$h] = $this->co();
        $dish = $this->postJson('/api/v1/catalog-items', ['kind' => 'menu', 'name' => 'Omelette', 'price' => 2000, 'station' => 'kitchen'], $h)->json('data');
        $egg = $this->postJson('/api/v1/inventory-items', ['name' => 'Egg', 'unit' => 'pcs', 'reorder_level' => 10, 'cost' => 150], $h)->json('data');
        $this->postJson('/api/v1/recipes', ['catalog_item_id' => $dish['id'], 'inventory_item_id' => $egg['id'], 'quantity' => 3], $h)->assertCreated();
        $this->postJson('/api/v1/stock/adjust', ['inventory_item_id' => $egg['id'], 'quantity' => 30, 'type' => 'purchase'], $h)->assertCreated()->assertJsonPath('level', 30);
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $dish['id'], 'quantity' => 2]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => $o['total'], 'method_type' => 'cash'], $h)->assertCreated();
        $stock = collect($this->getJson('/api/v1/stock', $h)->json('data'))->firstWhere('id', $egg['id']);
        $this->assertEquals(24, $stock['quantity']);
        $this->assertFalse($stock['low']);
        $this->postJson('/api/v1/stock/adjust', ['inventory_item_id' => $egg['id'], 'quantity' => 20, 'type' => 'waste'], $h)->assertCreated();
        $this->assertTrue(collect($this->getJson('/api/v1/stock?low_only=1', $h)->json('data'))->contains('id', $egg['id']));
        // movements cannot be edited through the generic API (not registered)
        $this->patchJson('/api/v1/stock-movements/x', [], $h)->assertStatus(404);
        $this->assertSame(3, $this->getJson('/api/v1/stock-movements', $h)->json('meta.total'));
    }

    public function test_stocktake_and_purchase_order_receipt_is_idempotent(): void
    {
        [$h] = $this->co();
        $item = $this->postJson('/api/v1/inventory-items', ['name' => 'Rice', 'unit' => 'kg'], $h)->json('data');
        $po = $this->postJson('/api/v1/purchase-orders', ['status' => 'ordered', 'lines' => [['inventory_item_id' => $item['id'], 'quantity' => 50, 'unit_cost' => 1200]]], $h)->assertCreated()->json('data');
        $this->assertSame(60000, $po['total']);
        $rcpt = ['receipt_key' => 'GRN1', 'receipts' => [['inventory_item_id' => $item['id'], 'quantity' => 20]]];
        $this->postJson("/api/v1/purchase-orders/{$po['id']}/receive", $rcpt, $h)->assertOk()->assertJsonPath('data.status', 'partial');
        $this->postJson("/api/v1/purchase-orders/{$po['id']}/receive", $rcpt, $h)->assertOk(); // replay: no double stock
        $this->assertEquals(20, collect($this->getJson('/api/v1/stock', $h)->json('data'))->firstWhere('id', $item['id'])['quantity']);
        $this->postJson("/api/v1/purchase-orders/{$po['id']}/receive", ['receipt_key' => 'GRN2', 'receipts' => [['inventory_item_id' => $item['id'], 'quantity' => 30]]], $h)->assertJsonPath('data.status', 'received');
        $this->postJson('/api/v1/stock/stocktake', ['counts' => [['inventory_item_id' => $item['id'], 'counted' => 47]]], $h)->assertOk()->assertJsonPath('data.0.difference', -3);
        $this->assertEquals(47, collect($this->getJson('/api/v1/stock', $h)->json('data'))->firstWhere('id', $item['id'])['quantity']);
    }

    public function test_storekeeper_permissions(): void
    {
        [$h, $tid] = $this->co();
        $sk = $this->userWithRole($tid, 'storekeeper', 'sk@example.com');
        $this->getJson('/api/v1/stock', $sk)->assertOk();
        $this->getJson('/api/v1/reports/sales', $sk)->assertStatus(403);
        $this->getJson('/api/v1/orders', $sk)->assertStatus(403);
    }

    public function test_attendance_payroll_and_leave(): void
    {
        [$h, $tid] = $this->co();
        $cashierHeaders = $this->userWithRole($tid, 'cashier', 'c@example.com');
        $uid = collect($this->getJson('/api/v1/users', $h)->json('data'))->firstWhere('email', 'c@example.com')['id'];
        $emp = $this->postJson('/api/v1/employees', ['name' => 'Cathy', 'user_id' => $uid, 'pay_type' => 'hourly', 'pay_rate' => 1000], $h)->assertCreated()->json('data');
        $this->postJson('/api/v1/attendance', ['type' => 'in', 'at' => '2027-01-05 08:00:00'], $cashierHeaders)->assertCreated();
        $this->postJson('/api/v1/attendance', ['type' => 'out', 'at' => '2027-01-05 16:30:00'], $cashierHeaders)->assertCreated();
        $dup = $this->postJson('/api/v1/attendance', ['type' => 'out', 'at' => '2027-01-05 17:00:00'], $cashierHeaders)->assertCreated();
        $this->assertTrue($dup->json('data.flagged'));
        $p = $this->getJson('/api/v1/payroll?from=2027-01-01&to=2027-01-31', $h)->assertOk()->json();
        $this->assertSame(8500, $p['data'][0]['amount']);
        $this->getJson('/api/v1/payroll?from=2027-01-01&to=2027-01-31&format=csv', $h)->assertOk();
        $this->getJson('/api/v1/payroll?from=2027-01-01&to=2027-01-31', $cashierHeaders)->assertStatus(403);
        $leave = $this->postJson('/api/v1/my/leave-requests', ['from_date' => '2027-02-01', 'to_date' => '2027-02-03'], $cashierHeaders)->assertCreated()->json('data');
        $this->postJson("/api/v1/leave-requests/{$leave['id']}/decide", ['status' => 'approved'], $cashierHeaders)->assertStatus(403);
        $this->postJson("/api/v1/leave-requests/{$leave['id']}/decide", ['status' => 'approved'], $h)->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson('/api/v1/attendance', ['type' => 'in', 'employee_id' => $emp['id']], $this->userWithRole($tid, 'waiter', 'w@example.com'))->assertStatus(403); // cannot clock someone else
    }

    public function test_housekeeping_flow_updates_room_status(): void
    {
        [$h, $tid] = $this->co();
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Std', 'base_price' => 1000], $h)->json('data');
        $room = $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '7', 'housekeeping_status' => 'dirty'], $h)->json('data');
        $hk = $this->userWithRole($tid, 'housekeeper', 'hk@example.com');
        $hkId = collect($this->getJson('/api/v1/users', $h)->json('data'))->firstWhere('email', 'hk@example.com')['id'];
        $task = $this->postJson('/api/v1/tasks', ['type' => 'clean', 'room_id' => $room['id'], 'title' => 'Clean 7', 'priority' => 'high'], $h)->json('data');
        $this->postJson("/api/v1/tasks/{$task['id']}/assign", ['assigned_to' => $hkId], $hk)->assertStatus(403);
        $this->postJson("/api/v1/tasks/{$task['id']}/assign", ['assigned_to' => $hkId], $h)->assertOk();
        $this->assertCount(1, $this->getJson('/api/v1/my-tasks', $hk)->json('data'));
        $this->postJson("/api/v1/tasks/{$task['id']}/start", [], $hk)->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/v1/tasks/{$task['id']}/complete", ['photos' => ['p1.jpg']], $hk)->assertOk()->assertJsonPath('data.status', 'done');
        $this->assertSame('clean', collect($this->getJson('/api/v1/room-board', $h)->json('data'))->firstWhere('number', '7')['housekeeping_status']);
        $this->postJson("/api/v1/tasks/{$task['id']}/inspect", [], $hk)->assertStatus(403);
        $this->postJson("/api/v1/tasks/{$task['id']}/inspect", [], $h)->assertOk();
        $this->assertSame('inspected', collect($this->getJson('/api/v1/room-board', $h)->json('data'))->firstWhere('number', '7')['housekeeping_status']);
    }

    public function test_reports_dashboard_sales_occupancy_and_profit(): void
    {
        [$h] = $this->co();
        $beer = $this->postJson('/api/v1/catalog-items', ['kind' => 'drink', 'name' => 'Primus', 'price' => 1500, 'station' => 'bar'], $h)->json('data');
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id'], 'quantity' => 4]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => 6000, 'method_type' => 'cash'], $h)->assertCreated();
        $this->postJson('/api/v1/expenses', ['category' => 'utilities', 'amount' => 2000, 'date' => now()->toDateString()], $h)->assertCreated();
        $dash = $this->getJson('/api/v1/reports/dashboard', $h)->assertOk()->json('data');
        $this->assertSame(6000, $dash['collected']['total']);
        $this->assertSame(1, $dash['orders']['paid_today']);
        $sales = $this->getJson('/api/v1/reports/sales?group=item', $h)->json('data');
        $this->assertSame('Primus', $sales[0]['key']);
        $this->assertSame(6000, (int) $sales[0]['amount']);
        $byDay = $this->getJson('/api/v1/reports/sales', $h)->json('data');
        $this->assertSame(6000, (int) $byDay[0]['amount']);
        $this->getJson('/api/v1/reports/sales?format=csv', $h)->assertOk();
        $this->assertSame(4000, $this->getJson('/api/v1/reports/profit', $h)->json('data.net'));
        // occupancy with a night-audited stay
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Std', 'base_price' => 50000], $h)->json('data');
        $room = $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '1'], $h)->json('data');
        $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '2'], $h);
        $res = $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'room_id' => $room['id'], 'check_in' => now()->toDateString(), 'check_out' => now()->addDay()->toDateString(), 'guest' => ['name' => 'A', 'id_number' => '1']], $h)->json('data');
        $this->postJson("/api/v1/reservations/{$res['id']}/check-in", [], $h)->assertOk();
        $this->postJson('/api/v1/night-audit', [], $h)->assertOk();
        $occ = $this->getJson('/api/v1/reports/occupancy?from='.now()->toDateString().'&to='.now()->toDateString(), $h)->assertOk()->json();
        $this->assertEquals(50.0, $occ['data'][0]['occupancy_pct']);
        $this->assertSame(50000, $occ['data'][0]['adr']);
        $this->assertSame(25000, $occ['data'][0]['revpar']);
        // waiter cannot see finance reports
    }
}
