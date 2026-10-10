<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Tests\TestCase;

class WebConsoleTest extends TestCase
{
    public function test_public_pages_render(): void
    {
        foreach (['/', '/features', '/solutions', '/pricing', '/contact', '/login', '/register', '/forgot-password', '/privacy', '/terms'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/api/v1/health')->assertOk();
    }

    public function test_console_requires_login(): void
    {
        $this->get('/app')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_register_logs_in_and_session_authenticates_the_api(): void
    {
        $this->postJson('/register', ['company' => 'Web Lodge', 'name' => 'Owner', 'email' => 'owner@web.test', 'password' => 'correct-horse-battery', 'modules' => ['rooms', 'tourism']])
            ->assertCreated()->assertJsonPath('redirect', '/app');
        $this->get('/app')->assertOk();
        $ctx = $this->withHeaders(['Referer' => 'http://localhost', 'Origin' => 'http://localhost'])->getJson('/api/v1/me/context')->assertOk();
        $this->assertSame('web-lodge', $ctx->json('tenant.slug'));
        $this->assertContains('tourism', $ctx->json('modules'));
        // writes work through the cookie session too (CSRF is enforced in production)
        $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/api/v1/guests', ['name' => 'Cookie guest'])->assertCreated();
    }

    public function test_web_login_with_two_factor_and_logout(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $secret = $this->postJson('/api/v1/auth/2fa/setup', [], $h)->json('secret');
        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => Totp::code($secret)], $h);
        $creds = ['email' => 'kivu-lodge@example.com', 'password' => 'correct-horse-battery'];
        $this->postJson('/login', $creds)->assertStatus(202)->assertJsonPath('two_factor', true);
        $this->postJson('/login', $creds + ['code' => '000000'])->assertStatus(422);
        $this->postJson('/login', $creds + ['code' => Totp::code($secret)])->assertOk()->assertJsonPath('redirect', '/app');
        $this->postJson('/logout')->assertOk();
        $this->get('/app')->assertRedirect('/login');
        $this->postJson('/login', ['email' => 'kivu-lodge@example.com', 'password' => 'wrong'])->assertStatus(422)->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_platform_and_tenant_users_are_kept_apart(): void
    {
        $this->registerCompany('Kivu Lodge');
        $admin = new User(['name' => 'Root', 'email' => 'root@edusmart.test', 'password' => 'super-secret-pass']);
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->postJson('/login', ['email' => 'root@edusmart.test', 'password' => 'super-secret-pass'])->assertOk()->assertJsonPath('redirect', '/admin');
        $this->get('/admin')->assertOk();
        $this->get('/app')->assertForbidden();
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => 'kivu-lodge@example.com', 'password' => 'correct-horse-battery'])->assertOk()->assertJsonPath('redirect', '/app');
        $this->get('/admin')->assertForbidden();
    }

    public function test_pricing_page_shows_live_plans(): void
    {
        $this->get('/pricing')->assertOk()->assertSee('Starter')->assertSee('30,000')->assertSee('Business');
    }

    public function test_contact_form_stores_message_and_blocks_bots(): void
    {
        $this->postJson('/contact', ['name' => 'Aline', 'email' => 'a@example.com', 'message' => 'Demo please', 'interest' => 'Hotel / lodge'])->assertCreated();
        $this->assertDatabaseHas('contact_messages', ['email' => 'a@example.com', 'interest' => 'Hotel / lodge']);
        $this->postJson('/contact', ['name' => 'Bot', 'email' => 'b@example.com', 'message' => 'spam', 'website' => 'http://spam'])->assertStatus(422);
        $this->postJson('/contact', ['name' => 'x'])->assertStatus(422);
    }

    public function test_each_workspace_is_gated_by_role(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge', null, ['modules' => ['rooms', 'pos', 'housekeeping', 'staff']]);
        $tid = $this->getJson('/api/v1/me/context', $h)->json('tenant.id');
        $this->get('/terminal')->assertRedirect('/login');
        $this->userWithRole($tid, 'kitchen', 'k@example.com');
        $this->userWithRole($tid, 'housekeeper', 'hk@example.com');
        $this->userWithRole($tid, 'waiter', 'w@example.com');
        $pw = 'correct-horse-battery';

        $this->postJson('/login', ['email' => 'k@example.com', 'password' => $pw])->assertOk();
        $this->get('/kds')->assertOk();
        $this->get('/terminal')->assertForbidden();   // kitchen cannot create orders
        $this->get('/desk')->assertForbidden();
        $this->postJson('/logout');

        $this->postJson('/login', ['email' => 'hk@example.com', 'password' => $pw])->assertOk();
        $this->get('/staff')->assertOk();
        $this->get('/kds')->assertForbidden();
        $this->postJson('/logout');

        $this->postJson('/login', ['email' => 'w@example.com', 'password' => $pw])->assertOk();
        $this->get('/terminal')->assertOk();
        $this->get('/workspaces')->assertOk();
        $this->postJson('/logout');

        $this->postJson('/login', ['email' => 'kivu-lodge@example.com', 'password' => $pw])->assertOk();
        foreach (['/app', '/terminal', '/kds', '/desk', '/staff', '/workspaces'] as $u) {
            $this->get($u)->assertOk();
        }
    }
}
