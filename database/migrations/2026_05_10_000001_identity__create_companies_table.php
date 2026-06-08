<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tin', 32);
            $table->string('rdo_code', 8);
            $table->string('registered_name');
            $table->string('trade_name')->nullable();
            $table->enum('taxpayer_type', ['large', 'medium', 'regular'])->default('regular');
            $table->enum('vat_status', ['vat', 'nonvat', 'exempt'])->default('vat');
            $table->text('address')->nullable();
            $table->string('telephone', 32)->nullable();
            $table->string('email')->nullable();
            $table->date('registered_on')->nullable();
            $table->string('cas_ptu_number', 64)->nullable();
            $table->date('cas_ptu_date')->nullable();
            $table->timestampsTz();

            $table->unique('tin');
            $table->index('rdo_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.companies');
    }
};
