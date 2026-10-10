<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class ConfigAndCrudTest extends TestCase
{
    public function test_owner_can_create_custom_roles_and_users_and_permissions_apply(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $role = $this->postJson('/api/v1/roles', ['key' => 'tour_desk', 'name' => 'Tour desk', 'permissions' => ['tourism.view', 'tourism.create']], $h)->assertCreated()->json('data');
        $this->postJson('/api/v1/roles', ['key' => 'bad', 'name' => 'Bad', 'permissions' => ['made.up']], $h)->assertStatus(422);
        $this->postJson('/api/v1/users', ['name' => 'Desk', 'email' => 'desk@example.com', 'password' => 'long-enough-pass', 'roles' => [$role['id']]], $h)->assertCreated();
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'desk@example.com', 'password' => 'long-enough-pass', 'device' => $this->device()])->assertOk();
        $desk = ['Authorization' => 'Bearer '.$login->json('access_token')];
        $this->getJson('/api/v1/users', $desk)->assertStatus(403);
        $this->postJson('/api/v1/settings', [], $desk)->assertStatus(405);
        $this->deleteJson('/api/v1/roles/'.$role['id'], [], $desk)->assertStatus(403);
    }

    public function test_module_gating_and_toggle(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $this->getJson('/api/v1/tourism-resources', $h)->assertStatus(403)->assertJsonPath('error.code', 'module_disabled');
        $this->putJson('/api/v1/modules/tourism', ['enabled' => true], $h)->assertOk();
        $this->getJson('/api/v1/tourism-resources', $h)->assertOk();
        $this->putJson('/api/v1/modules/tourism', ['enabled' => false], $h)->assertOk();
        $this->getJson('/api/v1/tourism-resources', $h)->assertStatus(403);
    }

    public function test_generic_crud_with_client_uuid_and_version_conflict(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $id = (string) Str::uuid7();
        $this->postJson('/api/v1/room-types', ['id' => $id, 'name' => 'Deluxe', 'base_price' => 85000], $h)->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.version', 1);
        $this->postJson('/api/v1/room-types', ['id' => $id, 'name' => 'Deluxe', 'base_price' => 85000], $h)->assertStatus(409);
        $this->patchJson("/api/v1/room-types/$id", ['base_price' => 90000, 'version' => 1], $h)->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson("/api/v1/room-types/$id", ['base_price' => 1, 'version' => 1], $h)->assertStatus(409)->assertJsonPath('error.code', 'version_conflict');
        $this->getJson('/api/v1/room-types?q=delu', $h)->assertJsonPath('meta.total', 1);
        $this->deleteJson("/api/v1/room-types/$id", [], $h)->assertNoContent();
        $this->getJson("/api/v1/room-types/$id", $h)->assertStatus(404);
    }

    public function test_cannot_reference_another_tenants_records(): void
    {
        [$ha] = $this->registerCompany('Alpha Hotel');
        [$hb] = $this->registerCompany('Beta Hotel');
        $type = $this->postJson('/api/v1/room-types', ['name' => 'Suite', 'base_price' => 1], $hb)->json('data');
        $this->postJson('/api/v1/rooms', ['room_type_id' => $type['id'], 'number' => '101'], $ha)->assertStatus(422);
        $this->getJson('/api/v1/room-types/'.$type['id'], $ha)->assertStatus(404);
        $this->patchJson('/api/v1/room-types/'.$type['id'], ['name' => 'Hacked'], $ha)->assertStatus(404);
        $this->deleteJson('/api/v1/room-types/'.$type['id'], [], $ha)->assertStatus(404);
        $this->assertSame(0, $this->getJson('/api/v1/room-types', $ha)->json('meta.total'));
    }

    public function test_custom_fields_validate_meta_per_tenant(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $this->postJson('/api/v1/custom-fields', ['entity' => 'guest', 'key' => 'passport', 'label' => ['en' => 'Passport', 'fr' => 'Passeport', 'rw' => 'Pasiporo'], 'type' => 'text', 'required' => true], $h)->assertCreated();
        $this->postJson('/api/v1/custom-fields', ['entity' => 'guest', 'key' => 'diet', 'label' => ['en' => 'Diet'], 'type' => 'select', 'options' => ['none', 'vegan']], $h)->assertCreated();
        $this->postJson('/api/v1/guests', ['name' => 'Aline'], $h)->assertStatus(422)->assertJsonPath('error.details', fn ($d) => isset($d['meta.passport']));
        $this->postJson('/api/v1/guests', ['name' => 'Aline', 'meta' => ['passport' => 'RW1', 'diet' => 'meat']], $h)->assertStatus(422);
        $this->postJson('/api/v1/guests', ['name' => 'Aline', 'meta' => ['passport' => 'RW1', 'diet' => 'vegan']], $h)->assertCreated()->assertJsonPath('data.meta.diet', 'vegan');
        // another tenant is not affected by these definitions
        [$hb] = $this->registerCompany('Beta Hotel');
        $this->postJson('/api/v1/guests', ['name' => 'Bob'], $hb)->assertCreated();
    }

    public function test_settings_and_terminology_overrides(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $this->putJson('/api/v1/settings', ['values' => ['terminology' => ['guest' => ['en' => 'Visitor']], 'rooms.checkin_time' => '12:00']], $h)->assertOk();
        $ctx = $this->getJson('/api/v1/me/context', $h)->json('settings');
        $this->assertSame('12:00', $ctx['rooms.checkin_time']);
        $this->assertSame('Visitor', $ctx['terminology']['guest']['en']);
        $this->putJson('/api/v1/settings', ['values' => ['bad key!' => 1]], $h)->assertStatus(422);
    }

    public function test_tax_rates_and_payment_methods_are_editable(): void
    {
        [$h] = $this->registerCompany('Kivu Lodge');
        $vat = collect($this->getJson('/api/v1/tax-rates', $h)->json('data'))->firstWhere('key', 'vat');
        $this->patchJson('/api/v1/tax-rates/'.$vat['id'], ['rate_bp' => 0], $h)->assertOk()->assertJsonPath('data.rate_bp', 0);
        $this->postJson('/api/v1/payment-methods', ['key' => 'airtel', 'name' => 'Airtel Money', 'type' => 'momo'], $h)->assertCreated();
    }
}
