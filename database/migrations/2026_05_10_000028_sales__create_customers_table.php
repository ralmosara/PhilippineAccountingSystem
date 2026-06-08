<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sales.customers — including senior-citizen / PWD flags for the
 * 20% discount + VAT exemption rules (RA 9994 / RA 10754).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales.customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('customer_no', 32);                        // CUST-000001
            $table->string('registered_name');                        // BIR-registered legal name
            $table->string('trade_name')->nullable();
            $table->string('tin', 32)->nullable();                    // null for individual non-business
            $table->boolean('is_vat_registered')->default(true);
            $table->boolean('is_government')->default(false);         // 5% withheld VAT applies
            $table->boolean('is_senior_citizen')->default(false);     // 20% disc + VAT exempt
            $table->boolean('is_pwd')->default(false);                // 20% disc + VAT exempt
            $table->string('id_type', 32)->nullable();                // for senior/PWD substantiation
            $table->string('id_number', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->smallInteger('payment_terms_days')->default(0);   // 0 = COD
            $table->char('default_currency', 3)->default('PHP');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'customer_no']);
            $table->unique(['company_id', 'tin']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'registered_name']);
        });

        Schema::create('sales.customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->enum('address_type', ['billing', 'shipping']);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('barangay', 64)->nullable();
            $table->string('city', 64);
            $table->string('province', 64)->nullable();
            $table->string('region', 32)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->foreign('customer_id')
                  ->references('id')->on('sales.customers')
                  ->onDelete('cascade');

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales.customer_addresses');
        Schema::dropIfExists('sales.customers');
    }
};
