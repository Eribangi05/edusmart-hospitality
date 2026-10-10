<?php

namespace Tests\Feature;

use App\Support\Totp;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    private function co(array $modules = ['rooms', 'pos', 'tourism', 'inventory', 'staff', 'housekeeping', 'finance']): array
    {
        [$h] = $this->registerCompany('Kivu Lodge', null, ['modules' => $modules]);
        $vat = collect($this->getJson('/api/v1/tax-rates', $h)->json('data'))->firstWhere('key', 'vat');
        $this->patchJson("/api/v1/tax-rates/{$vat['id']}", ['rate_bp' => 0], $h);

        return [$h, $this->getJson('/api/v1/me/context', $h)->json('tenant.id')];
    }

    public function test_vouchers_apply_discount_and_respect_rules(): void
    {
        [$h] = $this->co();
        $item = $this->postJson('/api/v1/catalog-items', ['kind' => 'menu', 'name' => 'Fish', 'price' => 10000], $h)->json('data');
        $this->postJson('/api/v1/vouchers', ['code' => 'WELCOME10', 'kind' => 'percent', 'value' => 1000, 'max_uses' => 1], $h)->assertCreated();
        $this->postJson('/api/v1/vouchers', ['code' => 'BIG', 'kind' => 'amount', 'value' => 2000, 'min_total' => 50000], $h)->assertCreated();
        $this->postJson('/api/v1/vouchers', ['code' => 'OLD', 'kind' => 'amount', 'value' => 500, 'valid_to' => '2020-01-01'], $h)->assertCreated();
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $item['id']]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/voucher", ['code' => 'nope'], $h)->assertStatus(422)->assertJsonPath('error.code', 'voucher_invalid');
        $this->postJson("/api/v1/orders/{$o['id']}/voucher", ['code' => 'OLD'], $h)->assertStatus(422)->assertJsonPath('error.code', 'voucher_expired');
        $this->postJson("/api/v1/orders/{$o['id']}/voucher", ['code' => 'BIG'], $h)->assertStatus(422)->assertJsonPath('error.code', 'voucher_min_total');
        $r = $this->postJson("/api/v1/orders/{$o['id']}/voucher", ['code' => 'WELCOME10'], $h)->assertOk();
        $this->assertSame(1000, $r->json('data.discount'));
        $this->assertSame(9000, $r->json('data.total'));
        $this->postJson("/api/v1/orders/{$o['id']}/voucher", ['code' => 'WELCOME10'], $h)->assertOk(); // same code again is a no-op
        $o2 = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $item['id']]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o2['id']}/voucher", ['code' => 'WELCOME10'], $h)->assertStatus(422)->assertJsonPath('error.code', 'voucher_used_up');
    }

    public function test_search_alerts_and_trends(): void
    {
        [$h, $tid] = $this->co();
        $this->postJson('/api/v1/guests', ['name' => 'Aline Uwase', 'phone' => '0788123456'], $h);
        $beer = $this->postJson('/api/v1/catalog-items', ['kind' => 'drink', 'name' => 'Primus', 'price' => 1500], $h)->json('data');
        $this->getJson('/api/v1/search?q=a', $h)->assertJsonPath('data', []);
        $hits = collect($this->getJson('/api/v1/search?q=Uwase', $h)->json('data'));
        $this->assertSame('guest', $hits->first()['type']);
        $this->assertTrue(collect($this->getJson('/api/v1/search?q=Prim', $h)->json('data'))->contains('type', 'item'));
        $kitchen = $this->userWithRole($tid, 'kitchen', 'k@example.com');
        $this->assertSame([], $this->getJson('/api/v1/search?q=Uwase', $kitchen)->json('data')); // no guest access

        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id'], 'quantity' => 4]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => 6000, 'method_type' => 'cash'], $h)->assertCreated();
        $t = $this->getJson('/api/v1/reports/trends?days=7', $h)->assertOk()->json('data');
        $this->assertCount(7, $t['series']);
        $this->assertSame(6000, end($t['series'])['collected']);
        $this->assertSame('Primus', $t['top_items'][0]['name']);
        $this->assertSame(1, array_sum($t['orders_by_hour']));

        $type = $this->postJson('/api/v1/room-types', ['name' => 'Std', 'base_price' => 50000], $h)->json('data');
        $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '1', 'housekeeping_status' => 'dirty'], $h);
        $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'check_in' => now()->toDateString(), 'check_out' => now()->addDay()->toDateString(), 'guest' => ['name' => 'Jean']], $h);
        $n = collect($this->getJson('/api/v1/notifications', $h)->assertOk()->json('data'));
        $this->assertTrue($n->contains('kind', 'arrivals'));
        $this->assertTrue($n->contains('kind', 'dirty'));
        $this->assertNotEmpty($this->getJson('/api/v1/activity', $h)->json('data'));
        $this->getJson('/api/v1/activity', $kitchen)->assertStatus(403);
    }

    public function test_desk_timeline_and_guest_profile(): void
    {
        [$h] = $this->co();
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Std', 'base_price' => 50000], $h)->json('data');
        $room = $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '7'], $h)->json('data');
        $today = now()->toDateString();
        $res = $this->postJson('/api/v1/reservations', ['room_type_id' => $type['id'], 'room_id' => $room['id'], 'check_in' => $today, 'check_out' => now()->addDays(2)->toDateString(), 'guest' => ['name' => 'Jean Kamali', 'phone' => '0788']], $h)->json('data');
        $desk = $this->getJson('/api/v1/desk', $h)->assertOk()->json('data');
        $this->assertSame('Jean Kamali', $desk['arrivals'][0]['guest']);
        $this->assertFalse($desk['arrivals'][0]['has_id']);
        $this->assertSame('7', $desk['arrivals'][0]['room']);
        $tl = $this->getJson("/api/v1/rooms/timeline?from=$today&days=7", $h)->assertOk()->json('data');
        $this->assertSame('7', $tl['rooms'][0]['number']);
        $this->assertSame($res['number'], $tl['rooms'][0]['reservations'][0]['number']);
        $this->assertSame([], $tl['unassigned']);
        $gid = $res['guest_id'];
        $p = $this->getJson("/api/v1/guests/$gid/profile", $h)->assertOk()->json('data');
        $this->assertSame('Jean Kamali', $p['guest']['name']);
        $this->assertSame('New', $p['stats']['tier']);
        $this->assertCount(1, $p['reservations']);
    }

    public function test_onboarding_checklist_progresses(): void
    {
        [$h] = $this->co();
        $a = $this->getJson('/api/v1/onboarding', $h)->assertOk()->json('data');
        $this->postJson('/api/v1/menu-not-a-route', [], $h);
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Std', 'base_price' => 1], $h)->json('data');
        $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '1'], $h);
        $b = $this->getJson('/api/v1/onboarding', $h)->json('data');
        $this->assertGreaterThan($a['done'], $b['done']);
        $this->assertTrue(collect($b['steps'])->firstWhere('key', 'rooms')['done']);
    }

    public function test_workspaces_follow_roles(): void
    {
        [$h, $tid] = $this->co();
        $keys = fn ($hd) => collect($this->getJson('/api/v1/me/context', $hd)->json('workspaces'))->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['console', 'terminal', 'kds', 'desk', 'staff'], $keys($h));
        $this->assertSame(['kds'], array_values(array_diff($keys($this->userWithRole($tid, 'kitchen', 'k@example.com')), ['staff'])));
        $this->assertContains('terminal', $keys($this->userWithRole($tid, 'waiter', 'w@example.com')));
        $this->assertNotContains('console', $keys($this->userWithRole($tid, 'waiter', 'w2@example.com')));
        $this->assertContains('desk', $keys($this->userWithRole($tid, 'receptionist', 'r@example.com')));
        // web login lands each role in the right place
        $this->postJson('/login', ['email' => 'k@example.com', 'password' => 'correct-horse-battery'])->assertOk()->assertJsonPath('redirect', fn ($v) => in_array($v, ['/kds', '/workspaces'], true));
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => 'kivu-lodge@example.com', 'password' => 'correct-horse-battery'])->assertOk()->assertJsonPath('redirect', '/app');
    }
}
