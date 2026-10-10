<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Support\Modules;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioner
{
    /** Creates a tenant with owner, default roles, modules, taxes, payment methods and settings. */
    public function register(array $d): array
    {
        return DB::transaction(function () use ($d) {
            $plan = Plan::where('key', 'trial')->first();
            $slug = $this->uniqueSlug($d['company']);
            $tenant = Tenant::create([
                'name' => $d['company'],
                'slug' => $slug,
                'plan_id' => $plan?->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(30),
                'default_locale' => $d['locale'] ?? 'en',
            ]);

            return app(Tenancy::class)->run($tenant, function () use ($tenant, $d) {
                foreach (Modules::switchable() as $key) {
                    TenantModule::create(['tenant_id' => $tenant->id, 'module_key' => $key, 'enabled' => in_array($key, $d['modules'] ?? ['rooms', 'pos'], true)]);
                }
                $roles = [];
                foreach (Modules::defaultRoles() as $key => [$name, $perms]) {
                    $roles[$key] = Role::create(['tenant_id' => $tenant->id, 'key' => $key, 'name' => $name, 'permissions' => $perms, 'is_system' => true]);
                }
                $owner = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null,
                    'password' => $d['password'], 'locale' => $d['locale'] ?? 'en',
                ]);
                $owner->roles()->attach($roles['owner']->id, ['id' => (string) Str::uuid7()]);
                $property = Property::create([
                    'tenant_id' => $tenant->id, 'name' => $d['property_name'] ?? $d['company'],
                    'type' => $d['property_type'] ?? 'hotel',
                ]);
                $this->seedDefaults($tenant);
                Audit::log('tenant.registered', 'tenant', $tenant->id, null, ['slug' => $tenant->slug], $tenant->id, $owner->id);

                return [$tenant, $owner, $property];
            });
        });
    }

    /** Sensible Rwanda defaults; everything here is editable by the tenant. */
    public function seedDefaults(Tenant $tenant): void
    {
        $taxes = [
            ['vat', 'VAT', 1800, 'tax', true],
            ['service', 'Service charge', 0, 'service_charge', false],
            ['tourism_levy', 'Tourism levy', 0, 'levy', false],
        ];
        foreach ($taxes as [$k, $n, $bp, $kind, $active]) {
            TaxRate::create(['tenant_id' => $tenant->id, 'key' => $k, 'name' => $n, 'rate_bp' => $bp, 'kind' => $kind, 'is_active' => $active]);
        }
        $methods = [['cash', 'Cash', 'cash'], ['momo', 'MTN Mobile Money', 'momo'], ['card', 'Card', 'card'],
            ['bank', 'Bank transfer', 'bank'], ['room', 'Charge to room', 'room_charge']];
        foreach ($methods as $i => [$k, $n, $t]) {
            PaymentMethod::create(['tenant_id' => $tenant->id, 'key' => $k, 'name' => $n, 'type' => $t, 'position' => $i]);
        }
        $settings = [
            'locale.supported' => ['en', 'fr', 'rw'],
            'rooms.checkin_time' => '14:00',
            'rooms.checkout_time' => '11:00',
            'rooms.require_id_on_checkin' => true,
            'pos.tips_enabled' => true,
            'pos.require_manager_pin_for_void' => true,
            'tourism.deposit_percent' => 30,
            'receipt.footer' => 'Thank you / Murakoze / Merci',
            'terminology' => new \stdClass, // per-tenant label overrides, e.g. {"guest":{"en":"Visitor"}}
        ];
        foreach ($settings as $key => $value) {
            Setting::create(['tenant_id' => $tenant->id, 'key' => $key, 'value' => $value]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 1;
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
