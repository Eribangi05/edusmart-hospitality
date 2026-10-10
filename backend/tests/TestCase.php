<?php

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->set(null);
        parent::tearDown();
    }

    /** Each HTTP call must resolve its own user (guards are cached across calls in one test). */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();
        app(Tenancy::class)->set(null);

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function device(): array
    {
        return ['id' => (string) Str::uuid(), 'name' => 'Test phone', 'platform' => 'android', 'app_version' => '1.0'];
    }

    /** Registers a company through the API and returns [headers, response data, device]. */
    protected function registerCompany(string $company = 'Kivu Lodge', ?string $email = null, array $extra = []): array
    {
        $device = $this->device();
        $email ??= Str::slug($company).'@example.com';
        $res = $this->postJson('/api/v1/auth/register', array_merge([
            'company' => $company, 'name' => 'Owner '.$company, 'email' => $email,
            'password' => 'correct-horse-battery', 'device' => $device,
        ], $extra));
        $res->assertCreated();
        $data = $res->json();
        app(Tenancy::class)->set(null);

        return [['Authorization' => 'Bearer '.$data['access_token']], $data, $device];
    }

    /** Creates a user with given role keys inside a tenant and returns auth headers. */
    protected function userWithRole(string $tenantId, string $roleKey, string $email): array
    {
        $tenant = Tenant::find($tenantId);
        $headers = app(Tenancy::class)->run($tenant, function () use ($roleKey, $email) {
            $user = User::create(['name' => ucfirst($roleKey), 'email' => $email, 'password' => 'correct-horse-battery']);
            $user->roles()->attach(Role::where('key', $roleKey)->firstOrFail()->id, ['id' => (string) Str::uuid7()]);

            return $user;
        });
        $res = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'correct-horse-battery', 'device' => $this->device()]);
        $res->assertOk();
        app(Tenancy::class)->set(null);

        return ['Authorization' => 'Bearer '.$res->json('access_token')];
    }
}
