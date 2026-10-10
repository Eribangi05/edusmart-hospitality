<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SecurityPrivacyTest extends TestCase
{
    public function test_login_locks_out_after_repeated_failures(): void
    {
        $this->registerCompany('Kivu Lodge');
        $bad = ['email' => 'kivu-lodge@example.com', 'password' => 'nope-nope-nope', 'device' => $this->device()];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $bad)->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/login', $bad)->assertStatus(429)->assertJsonPath('error.code', 'too_many_attempts');
        // even the right password is refused while locked
        $this->postJson('/api/v1/auth/login', ['password' => 'correct-horse-battery'] + $bad)->assertStatus(429);
        // another account is unaffected
        $this->registerCompany('Other Hotel');
    }

    public function test_password_reset_flow_revokes_sessions(): void
    {
        Notification::fake();
        [$h, , $device] = $this->registerCompany('Kivu Lodge');
        $this->postJson('/api/v1/auth/forgot', ['email' => 'nobody@example.com'])->assertOk(); // no account enumeration
        Notification::assertNothingSent();
        $this->postJson('/api/v1/auth/forgot', ['email' => 'kivu-lodge@example.com'])->assertOk();
        $token = null;
        Notification::assertSentTo(User::where('email', 'kivu-lodge@example.com')->first(), ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->postJson('/api/v1/auth/reset', ['email' => 'kivu-lodge@example.com', 'token' => 'wrong', 'password' => 'brand-new-password'])->assertStatus(422)->assertJsonPath('error.code', 'invalid_reset_token');
        $this->postJson('/api/v1/auth/reset', ['email' => 'kivu-lodge@example.com', 'token' => $token, 'password' => 'brand-new-password'])->assertOk();
        $this->getJson('/api/v1/me/context', $h)->assertStatus(401); // old sessions are gone
        $this->postJson('/api/v1/auth/login', ['email' => 'kivu-lodge@example.com', 'password' => 'correct-horse-battery', 'device' => $device])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => 'kivu-lodge@example.com', 'password' => 'brand-new-password', 'device' => $device])->assertOk();
        // the token cannot be replayed
        $this->postJson('/api/v1/auth/reset', ['email' => 'kivu-lodge@example.com', 'token' => $token, 'password' => 'another-new-password'])->assertStatus(422);
    }

    public function test_security_headers_are_set(): void
    {
        $r = $this->get('/login');
        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->getJson('/api/v1/health')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_owner_can_export_all_company_data_and_nothing_of_others(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$o] = $this->registerCompany('Other Hotel');
        $this->postJson('/api/v1/guests', ['name' => 'Aline Mine'], $h)->assertCreated();
        $this->postJson('/api/v1/guests', ['name' => 'Bob Secret'], $o)->assertCreated();
        $tid = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $waiter = $this->userWithRole($tid, 'waiter', 'w@example.com');
        $this->get('/api/v1/data-export', $waiter)->assertStatus(403);
        $body = $this->get('/api/v1/data-export', $h)->assertOk()->streamedContent();
        $this->assertStringContainsString('Aline Mine', $body);
        $this->assertStringNotContainsString('Bob Secret', $body);
        $this->assertStringNotContainsString('password', $body);
        $lines = array_filter(explode("\n", $body));
        $this->assertSame('tenant', json_decode($lines[0], true)['entity']);
    }

    public function test_tenant_erasure_removes_everything_and_only_that_tenant(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        [$o] = $this->registerCompany('Other Hotel');
        $this->postJson('/api/v1/guests', ['name' => 'Gone'], $h);
        $this->postJson('/api/v1/guests', ['name' => 'Stays'], $o);
        $this->postJson('/api/v1/tenant/deletion-request', ['confirm' => 'DELETE', 'reason' => 'closing'], $h)->assertOk();
        $this->assertNotNull(Tenant::where('slug', 'kivu-lodge')->first()->meta['deletion_requested_at']);

        Artisan::call('tenant:delete', ['slug' => 'kivu-lodge', '--force' => true]);
        $this->assertNull(Tenant::withTrashed()->where('slug', 'kivu-lodge')->first());
        $this->assertSame(0, DB::table('guests')->where('name', 'Gone')->count());
        $this->assertSame(0, DB::table('users')->where('email', 'kivu-lodge@example.com')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'tenant.registered')->where('after', 'like', '%kivu-lodge%')->count());
        $this->assertSame(1, DB::table('guests')->where('name', 'Stays')->count());
        $this->getJson('/api/v1/me/context', $h)->assertStatus(401);
        $this->getJson('/api/v1/guests', $o)->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_platform_erase_requires_typed_slug(): void
    {
        $this->registerCompany('Kivu Lodge');
        $admin = new User(['name' => 'Root', 'email' => 'root@edusmart.test', 'password' => 'super-secret-pass']);
        $admin->forceFill(['is_platform_admin' => true])->save();
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'root@edusmart.test', 'password' => 'super-secret-pass', 'device' => $this->device()])->assertOk();
        $h = ['Authorization' => 'Bearer '.$login->json('access_token')];
        $secret = $this->postJson('/api/v1/admin/2fa/setup', [], $h)->json('secret');
        $this->postJson('/api/v1/admin/2fa/confirm', ['code' => Totp::code($secret)], $h);
        $id = Tenant::first()->id;
        $this->deleteJson("/api/v1/admin/tenants/$id", ['confirm_slug' => 'wrong'], $h)->assertStatus(422);
        $this->deleteJson("/api/v1/admin/tenants/$id", ['confirm_slug' => 'kivu-lodge'], $h)->assertOk();
        $this->assertSame(0, Tenant::withTrashed()->count());
    }

    public function test_openapi_document_is_generated_from_routes(): void
    {
        $out = sys_get_temp_dir().'/openapi-test.json';
        Artisan::call('api:openapi', ['--out' => $out]);
        $doc = json_decode(file_get_contents($out), true);
        $this->assertSame('3.1.0', $doc['openapi']);
        $this->assertArrayHasKey('/orders/{id}/pay', $doc['paths']);
        $this->assertSame([], $doc['paths']['/auth/login']['post']['security']);
        $this->assertContains('pos.create', $doc['paths']['/orders']['post']['x-required-permission-any-of']);
        $this->assertSame('tourism', $doc['paths']['/bookings']['post']['x-module']);
    }
}
