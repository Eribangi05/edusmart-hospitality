<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Tenant;
use App\Support\Tenancy;
use App\Support\Totp;
use Tests\TestCase;

class AuthAndTenancyTest extends TestCase
{
    public function test_registration_creates_workspace_with_defaults(): void
    {
        [$h, $data] = $this->registerCompany('Kivu Lodge');
        $ctx = $this->getJson('/api/v1/me/context', $h)->assertOk()->json();

        $this->assertSame('RWF', $ctx['tenant']['currency']);
        $this->assertContains('rooms', $ctx['modules']);
        $this->assertContains('pos', $ctx['modules']);
        $this->assertNotContains('tourism', $ctx['modules']);
        $this->assertSame(['*'], $ctx['permissions']);
        $this->assertCount(5, $ctx['payment_methods']);
        $this->assertSame(1800, collect($ctx['tax_rates'])->firstWhere('key', 'vat')['rate_bp']);
        $this->assertSame(['en', 'fr', 'rw'], $ctx['settings']['locale.supported']);
    }

    public function test_tenant_can_choose_tourism_module_at_signup(): void
    {
        [$h] = $this->registerCompany('Volcano Treks', null, ['modules' => ['tourism', 'rooms']]);
        $ctx = $this->getJson('/api/v1/me/context', $h)->json();
        $this->assertContains('tourism', $ctx['modules']);
    }

    public function test_login_failure_and_validation_use_error_envelope(): void
    {
        $this->registerCompany('Kivu Lodge');
        $this->postJson('/api/v1/auth/login', ['email' => 'kivu-lodge@example.com', 'password' => 'wrong-password', 'device' => $this->device()])
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid_credentials');
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->getJson('/api/v1/me/context')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_tenant_a_cannot_see_tenant_b_data(): void
    {
        [$ha] = $this->registerCompany('Alpha Hotel');
        [$hb, $db] = $this->registerCompany('Beta Hotel');

        $propsA = $this->getJson('/api/v1/me/context', $ha)->json('properties');
        $propsB = $this->getJson('/api/v1/me/context', $hb)->json('properties');
        $this->assertNotEmpty($propsA);
        $this->assertSame([], array_values(array_intersect(array_column($propsA, 'id'), array_column($propsB, 'id'))));

        app(Tenancy::class)->set(null); // model level: no tenant context returns nothing
        $this->assertSame(0, Property::count());
        $alpha = Tenant::where('slug', 'alpha-hotel')->first();
        $beta = Tenant::where('slug', 'beta-hotel')->first();
        $betaProp = app(Tenancy::class)->run($beta, fn () => Property::first());
        app(Tenancy::class)->run($alpha, function () use ($betaProp) {
            $this->assertNull(Property::find($betaProp->id));
            $this->assertSame(1, Property::count());
        });
    }

    public function test_client_supplied_tenant_id_is_ignored_on_create(): void
    {
        $this->registerCompany('Alpha Hotel');
        $this->registerCompany('Beta Hotel');
        $alpha = Tenant::where('slug', 'alpha-hotel')->first();
        $other = Tenant::where('slug', 'beta-hotel')->first();
        app(Tenancy::class)->run($alpha, function () use ($other) {
            $s = Setting::create(['tenant_id' => $other->id, 'key' => 'x', 'value' => 1]);
            $this->assertSame(Tenant::where('slug', 'alpha-hotel')->first()->id, $s->tenant_id);
        });
    }

    public function test_permissions_are_enforced_per_role(): void
    {
        [$h, $data] = $this->registerCompany('Kivu Lodge');
        $tenantId = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $waiter = $this->userWithRole($tenantId, 'waiter', 'waiter@example.com');
        $this->getJson('/api/v1/devices', $waiter)->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        $this->getJson('/api/v1/devices', $h)->assertOk();
    }

    public function test_device_revoke_blocks_access_immediately(): void
    {
        [$h, $data, $device] = $this->registerCompany('Kivu Lodge');
        $tenantId = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $staffHeaders = $this->userWithRole($tenantId, 'manager', 'm@example.com');
        $devices = $this->getJson('/api/v1/devices', $h)->json('data');
        $target = collect($devices)->firstWhere('id', $device['id']);
        $this->postJson("/api/v1/devices/{$target['id']}/revoke", [], $staffHeaders)->assertNoContent();
        $this->getJson('/api/v1/me/context', $h)->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token'], 'device_id' => $device['id']])->assertStatus(401);
    }

    public function test_refresh_rotates_and_reuse_revokes_device(): void
    {
        [$h, $data, $device] = $this->registerCompany('Kivu Lodge');
        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token'], 'device_id' => $device['id']])->assertOk();
        $this->assertNotSame($data['refresh_token'], $r1->json('refresh_token'));
        // replay of the old token => theft assumed
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token'], 'device_id' => $device['id']])
            ->assertStatus(401)->assertJsonPath('error.code', 'device_revoked');
        $this->getJson('/api/v1/me/context', ['Authorization' => 'Bearer '.$r1->json('access_token')])->assertStatus(401);
    }

    public function test_two_factor_flow(): void
    {
        [$h, , $device] = $this->registerCompany('Kivu Lodge');
        $secret = $this->postJson('/api/v1/auth/2fa/setup', [], $h)->assertOk()->json('secret');
        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'], $h)->assertStatus(422);
        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => Totp::code($secret)], $h)->assertNoContent();

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'kivu-lodge@example.com', 'password' => 'correct-horse-battery', 'device' => $device])->assertStatus(202);
        $cid = $login->json('challenge_id');
        $this->postJson('/api/v1/auth/2fa', ['challenge_id' => $cid, 'code' => '000000'])->assertStatus(422);
        $this->postJson('/api/v1/auth/2fa', ['challenge_id' => $cid, 'code' => Totp::code($secret)])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
    }

    public function test_suspended_tenant_is_read_only(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        Tenant::where('slug', 'kivu-lodge')->update(['status' => 'suspended']);
        $this->getJson('/api/v1/me/context', $h)->assertOk();
        $this->postJson('/api/v1/auth/2fa/setup', [], $h)->assertStatus(402)->assertJsonPath('error.code', 'subscription_suspended');
    }

    public function test_audit_log_is_immutable(): void
    {
        $this->registerCompany('Kivu Lodge');
        $log = AuditLog::first();
        $this->assertNotNull($log);
        $this->assertFalse($log->update(['action' => 'tampered']));
        $this->assertFalse($log->delete());
    }
}
