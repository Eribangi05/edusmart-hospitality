<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('key')->unique();
            $t->string('name');
            $t->unsignedBigInteger('price_monthly')->default(0); // whole RWF
            $t->json('modules')->nullable();  // module keys included
            $t->json('limits')->nullable();   // max_users, max_properties...
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('tenants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $t->string('status')->default('trial'); // trial|active|grace|suspended
            $t->timestamp('trial_ends_at')->nullable();
            $t->string('country', 2)->default('RW');
            $t->string('currency', 3)->default('RWF');
            $t->string('default_locale', 5)->default('en');
            $t->string('timezone')->default('Africa/Kigali');
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('tenant_modules', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('module_key');
            $t->boolean('enabled')->default(true);
            $t->json('config')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'module_key']);
        });

        Schema::create('properties', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type')->default('hotel'); // hotel|guesthouse|restaurant|bar|shop|tour_operator|lodge|other
            $t->string('code')->nullable();
            $t->string('address')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('timezone')->nullable();
            $t->json('config')->nullable();
            $t->json('meta')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = platform staff
            $t->string('name');
            $t->string('email')->unique();
            $t->string('phone')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('pin_hash')->nullable();
            $t->string('locale', 5)->nullable();
            $t->text('totp_secret')->nullable();
            $t->timestamp('totp_confirmed_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_platform_admin')->default(false);
            $t->json('meta')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('name');
            $t->string('description')->nullable();
            $t->boolean('is_system')->default(false);
            $t->json('permissions'); // ["rooms.view","pos.void","*"]
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });

        Schema::create('role_user', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('property_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });

        Schema::create('devices', function (Blueprint $t) {
            $t->uuid('id')->primary(); // client-generated
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('platform')->default('android');
            $t->string('app_version')->nullable();
            $t->text('push_token')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('refresh_tokens', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->uuid('device_id')->index();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });

        Schema::create('two_factor_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->json('device');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id')->nullable()->index();
            $t->uuid('user_id')->nullable()->index();
            $t->uuid('device_id')->nullable();
            $t->string('action');
            $t->string('entity')->nullable();
            $t->string('entity_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'entity', 'entity_id']);
        });

        // ---- Flexibility layer -------------------------------------------------
        Schema::create('settings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('property_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->json('value')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'property_id', 'key']);
        });

        Schema::create('custom_fields', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('entity');          // guest|reservation|catalog_item|order|booking|employee|...
            $t->string('key');
            $t->json('label');             // {"en":"Passport no","fr":"...","rw":"..."}
            $t->string('type')->default('text'); // text|number|date|select|multiselect|boolean|textarea
            $t->json('options')->nullable();
            $t->boolean('required')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'entity', 'key']);
        });

        Schema::create('tax_rates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('name');
            $t->unsignedInteger('rate_bp')->default(0); // basis points: 1800 = 18%
            $t->string('kind')->default('tax');   // tax|service_charge|levy
            $t->boolean('inclusive')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });

        Schema::create('payment_methods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('name');
            $t->string('type')->default('cash'); // cash|momo|card|bank|room_charge|voucher|other
            $t->json('config')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['payment_methods', 'tax_rates', 'custom_fields', 'settings', 'audit_logs', 'two_factor_challenges',
            'refresh_tokens', 'devices', 'role_user', 'roles', 'sessions', 'password_reset_tokens', 'users',
            'properties', 'tenant_modules', 'tenants', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
