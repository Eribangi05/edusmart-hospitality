<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Support\Totp;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSyncBillingTest extends TestCase
{
    private function admin(): array
    {
        $u = new User(['name' => 'Root', 'email' => 'root@edusmart.test', 'password' => 'super-secret-pass']);
        $u->forceFill(['is_platform_admin' => true])->save();
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'root@edusmart.test', 'password' => 'super-secret-pass', 'device' => $this->device()])->assertOk();

        return [['Authorization' => 'Bearer '.$login->json('access_token')]];
    }

    private function enable2fa(array $h): void
    {
        $secret = $this->postJson('/api/v1/admin/2fa/setup', [], $h)->assertOk()->json('secret');
        $this->postJson('/api/v1/admin/2fa/confirm', ['code' => Totp::code($secret)], $h)->assertNoContent();
    }

    public function test_platform_console_requires_platform_admin_and_2fa(): void
    {
        [$tenantH] = $this->registerCompany('Kivu Lodge');
        $this->getJson('/api/v1/admin/metrics', $tenantH)->assertStatus(403);
        [$h] = $this->admin();
        $this->getJson('/api/v1/admin/metrics', $h)->assertStatus(403)->assertJsonPath('error.code', 'two_factor_required');
        $this->enable2fa($h);
        $m = $this->getJson('/api/v1/admin/metrics', $h)->assertOk()->json('data');
        $this->assertSame(1, $m['tenants_total']);
        // platform tokens cannot be used on tenant routes
        $this->getJson('/api/v1/me/context', $h)->assertStatus(403);
    }

    public function test_suspend_impersonate_and_plan_change(): void
    {
        [$tenantH] = $this->registerCompany('Kivu Lodge', null, ['modules' => ['rooms', 'pos', 'tourism']]);
        [$h] = $this->admin();
        $this->enable2fa($h);
        $t = $this->getJson('/api/v1/admin/tenants?q=kivu', $h)->json('data.0');
        $this->postJson("/api/v1/admin/tenants/{$t['id']}/status", ['status' => 'suspended', 'reason' => 'unpaid'], $h)->assertOk();
        $this->postJson('/api/v1/auth/2fa/setup', [], $tenantH)->assertStatus(402);
        $this->postJson("/api/v1/admin/tenants/{$t['id']}/status", ['status' => 'active'], $h)->assertOk();
        $starter = Plan::where('key', 'starter')->first();
        $this->postJson("/api/v1/admin/tenants/{$t['id']}/plan", ['plan_id' => $starter->id], $h)->assertOk();
        $this->getJson('/api/v1/tourism/today', $tenantH)->assertStatus(403); // starter has no tourism; module switched off
        $this->postJson("/api/v1/admin/tenants/{$t['id']}/impersonate", [], $h)->assertStatus(422);
        $imp = $this->postJson("/api/v1/admin/tenants/{$t['id']}/impersonate", ['reason' => 'support ticket 12'], $h)->assertOk();
        $this->getJson('/api/v1/me/context', ['Authorization' => 'Bearer '.$imp->json('access_token')])->assertOk()->assertJsonPath('tenant.slug', 'kivu-lodge');
        $logs = $this->getJson('/api/v1/audit-log?action=platform.impersonation', $tenantH)->json('data');
        $this->assertCount(1, $logs);
    }

    public function test_module_cannot_be_enabled_beyond_plan(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $t = Tenant::first();
        $t->forceFill(['plan_id' => Plan::where('key', 'starter')->first()->id])->save();
        $this->putJson('/api/v1/modules/tourism', ['enabled' => true], $h)->assertStatus(402)->assertJsonPath('error.code', 'plan_limit');
    }

    public function test_billing_invoice_mobile_money_payment_and_lifecycle(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $billing = $this->getJson('/api/v1/billing', $h)->assertOk()->json('data');
        $business = collect($billing['plans'])->firstWhere('key', 'business');
        $inv = $this->postJson('/api/v1/billing/plan', ['plan_id' => $business['id']], $h)->assertCreated()->json('data');
        $this->assertSame(80000, $inv['amount']);
        $this->postJson("/api/v1/billing/invoices/{$inv['id']}/pay", ['phone' => '250788000001'], $h)->assertOk()->assertJsonPath('data.payment_status', 'pending');
        $this->getJson("/api/v1/billing/invoices/{$inv['id']}", $h)->assertJsonPath('data.status', 'paid');
        $b = $this->getJson('/api/v1/billing', $h)->json('data');
        $this->assertSame('active', $b['status']);
        $this->assertSame('business', $b['plan']['key']);
        $this->assertNotNull($b['paid_until']);
        // trial plan includes everything; business excludes finance => switched off
        $this->getJson('/api/v1/reports/sales', $h)->assertStatus(403);
    }

    public function test_grace_then_suspension_and_suspended_tenant_can_still_pay(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $t = Tenant::first();
        $t->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $this->assertSame(['invoiced' => 0, 'grace' => 1, 'suspended' => 0], app(BillingService::class)->run());
        $this->assertSame('grace', $t->fresh()->status);
        $this->postJson('/api/v1/guests', ['name' => 'Still works in grace'], $h)->assertCreated();
        $t->fresh()->forceFill(['grace_ends_at' => now()->subHour()])->save();
        $this->assertSame(1, app(BillingService::class)->run()['suspended']);
        $this->postJson('/api/v1/guests', ['name' => 'x'], $h)->assertStatus(402);
        $this->getJson('/api/v1/guests', $h)->assertOk(); // read-only
        $plan = Plan::where('key', 'starter')->first();
        $inv = $this->postJson('/api/v1/billing/plan', ['plan_id' => $plan->id], $h)->assertCreated()->json('data');
        $this->postJson("/api/v1/billing/invoices/{$inv['id']}/pay", ['phone' => '250788000001'], $h)->assertOk();
        $this->getJson("/api/v1/billing/invoices/{$inv['id']}", $h)->assertJsonPath('data.status', 'paid');
        $this->postJson('/api/v1/guests', ['name' => 'Back online'], $h)->assertCreated();
    }

    public function test_momo_webhook_requires_secret_and_settles_payment(): void
    {
        config(['momo.webhook_token' => 'sekret']);
        [$h] = $this->registerCompany('Kivu Lodge');
        $beer = $this->postJson('/api/v1/catalog-items', ['kind' => 'drink', 'name' => 'Beer', 'price' => 1000], $h)->json('data');
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $beer['id']]]], $h)->json('data');
        $p = $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => $o['total'], 'method_type' => 'momo', 'payer_phone' => '250788123456'], $h)->json('payment');
        $this->assertSame('pending', $p['status']);
        $this->postJson('/api/v1/webhooks/momo/wrong', ['referenceId' => $p['reference']])->assertStatus(404);
        $this->postJson('/api/v1/webhooks/momo/sekret', ['referenceId' => $p['reference']])->assertOk();
        $this->assertSame('paid', $this->getJson("/api/v1/orders/{$o['id']}", $h)->json('data.status'));
    }

    // ----------------------------- sync -----------------------------
    public function test_sync_pull_returns_changes_with_cursors_and_tombstones(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $g = $this->postJson('/api/v1/guests', ['name' => 'A'], $h)->json('data');
        $this->postJson('/api/v1/guests', ['name' => 'B'], $h);
        $r1 = $this->getJson('/api/v1/sync/pull?entities=guests', $h)->assertOk();
        $this->assertCount(2, $r1->json('changes.guests'));
        $cursors = $r1->json('cursors');
        $this->assertCount(0, $this->getJson('/api/v1/sync/pull?entities=guests&cursors='.urlencode($cursors), $h)->json('changes.guests'));
        sleep(1);
        $this->deleteJson("/api/v1/guests/{$g['id']}", [], $h)->assertNoContent();
        $r2 = $this->getJson('/api/v1/sync/pull?entities=guests&cursors='.urlencode($cursors), $h)->json('changes.guests');
        $this->assertCount(1, $r2);
        $this->assertNotNull($r2[0]['deleted_at']); // tombstone
    }

    public function test_sync_pull_respects_modules_and_permissions_and_tenant(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$other] = $this->registerCompany('Other');
        $this->postJson('/api/v1/guests', ['name' => 'Mine'], $h);
        $resp = $this->getJson('/api/v1/sync/pull', $h);
        $resp->assertOk();
        $changes = $resp->json('changes');
        $this->assertArrayNotHasKey('bookings', $changes);       // tourism disabled
        $this->assertArrayNotHasKey('inventory_items', $changes); // inventory disabled
        $this->assertArrayHasKey('catalog_items', $changes);
        $this->assertCount(0, $this->getJson('/api/v1/sync/pull?entities=guests', $other)->json('changes.guests'));
        $tid = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $kitchen = $this->userWithRole($tid, 'kitchen', 'k@example.com');
        $kr = $this->getJson('/api/v1/sync/pull', $kitchen);
        $kr->assertOk();
        $this->assertArrayNotHasKey('guests', $kr->json('changes'));
    }

    public function test_sync_push_replays_offline_outbox_idempotently(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $beer = $this->postJson('/api/v1/catalog-items', ['kind' => 'drink', 'name' => 'Beer', 'price' => 1000], $h)->json('data');
        $orderId = (string) Str::uuid7();
        $items = [
            ['id' => $orderId, 'entity' => 'order', 'op' => 'create', 'idempotency_key' => 'k-1', 'payload' => ['number' => 'D3-0001', 'items' => [['catalog_item_id' => $beer['id'], 'quantity' => 2]]]],
            ['id' => 'pay-1', 'entity' => 'order', 'op' => 'update', 'idempotency_key' => 'k-2', 'payload' => ['id' => $orderId, 'action' => 'pay', 'amount' => 2360, 'method_type' => 'cash']],
            ['id' => 'bad-1', 'entity' => 'nonsense', 'op' => 'create', 'idempotency_key' => 'k-3', 'payload' => []],
            ['id' => 'pay-2', 'entity' => 'order', 'op' => 'update', 'idempotency_key' => 'k-4', 'payload' => ['id' => $orderId, 'action' => 'pay', 'amount' => 10, 'method_type' => 'cash']],
        ];
        $res = $this->postJson('/api/v1/sync/push', ['items' => $items], $h)->assertOk()->json('results');
        $this->assertSame(['applied', 'applied', 'rejected', 'conflict'], array_column($res, 'status'));
        $this->assertSame('unsupported_operation', $res[2]['reason']);
        $this->assertSame('paid', $this->getJson("/api/v1/orders/$orderId", $h)->json('data.status'));
        // whole batch again => duplicates, no double effects
        $res2 = $this->postJson('/api/v1/sync/push', ['items' => $items], $h)->assertOk()->json('results');
        $this->assertSame(['duplicate', 'duplicate', 'rejected', 'duplicate'], array_column($res2, 'status'));
        $this->assertSame(1, $this->getJson('/api/v1/orders', $h)->json('meta.total'));
        $this->assertSame(1, count($this->getJson("/api/v1/orders/$orderId", $h)->json('data.payments')));
    }

    public function test_sync_push_still_enforces_permissions(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $tid = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $kitchen = $this->userWithRole($tid, 'kitchen', 'k@example.com');
        $res = $this->postJson('/api/v1/sync/push', ['items' => [['id' => 'g1', 'entity' => 'guest', 'op' => 'create', 'idempotency_key' => 'x1', 'payload' => ['name' => 'Hack']]]], $kitchen)->json('results');
        $this->assertSame('rejected', $res[0]['status']);
        $this->assertSame('forbidden', $res[0]['reason']);
    }
}
