<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Tests\TestCase;

class WebConsoleTest extends TestCase
{
    public function test_public_pages_render(): void
    {
        foreach (['/', '/login', '/register', '/privacy', '/terms'] as $url) {
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
}
