<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->timestamp('paid_until')->nullable();
            $t->timestamp('grace_ends_at')->nullable();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('number')->unique();
            $t->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $t->json('lines'); // [{description, amount}]
            $t->unsignedBigInteger('amount'); // whole RWF
            $t->string('currency', 3)->default('RWF');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('due_on');
            $t->string('status')->default('pending'); // pending|paid|void
            $t->string('payment_reference')->nullable();
            $t->string('payer_phone')->nullable();
            $t->string('payment_status')->nullable(); // pending|successful|failed
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        // Remembers the outcome of every pushed item so replays are answered identically.
        Schema::create('sync_receipts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('idempotency_key', 120);
            $t->uuid('device_id')->nullable();
            $t->unsignedSmallInteger('status_code');
            $t->json('response')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_receipts');
        Schema::dropIfExists('invoices');
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['paid_until', 'grace_ends_at']));
    }
};
