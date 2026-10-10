<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->uuid('property_id')->nullable()->index();
            $t->string('code');
            $t->string('name')->nullable();
            $t->string('kind')->default('percent'); // percent | amount
            $t->unsignedBigInteger('value');         // whole percent (10 = 10%) or whole RWF
            $t->unsignedBigInteger('min_total')->default(0);
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('uses')->default(0);
            $t->boolean('is_active')->default(true);
            $t->json('meta')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'code']);
            $t->index(['tenant_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
