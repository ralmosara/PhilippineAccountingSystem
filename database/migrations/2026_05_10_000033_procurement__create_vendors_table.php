<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * procurement.vendors plus addresses and bank accounts.
 *
 * `is_top_withholding_agent` flags suppliers designated by BIR as TWAs;
 * mainly relevant when *we* sell to them and they withhold from our
 * payments, but we also store it on our vendor records for our own
 * payment-side withholding decisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement.vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('vendor_no', 32);
            $table->string('registered_name');
            $table->string('trade_name')->nullable();
            $table->string('tin', 32)->nullable();
            $table->boolean('is_vat_registered')->default(true);
            $table->boolean('is_government_supplier')->default(false);
            $table->boolean('is_top_withholding_agent')->default(false);
            $table->string('default_atc_code', 16)->nullable();      // logical ref to tax.atc_codes
            $table->decimal('default_withholding_rate', 8, 4)->nullable();
            $table->smallInteger('payment_terms_days')->default(30);
            $table->char('default_currency', 3)->default('PHP');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'vendor_no']);
            $table->unique(['company_id', 'tin']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('procurement.vendor_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id');
            $table->enum('address_type', ['main', 'billing', 'shipping']);
            $table->string('line1');
            $table->string('barangay', 64)->nullable();
            $table->string('city', 64);
            $table->string('province', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->timestampsTz();

            $table->foreign('vendor_id')
                  ->references('id')->on('procurement.vendors')
                  ->onDelete('cascade');
        });

        Schema::create('procurement.vendor_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id');
            $table->string('bank_name');
            $table->string('account_no_encrypted');                 // pgcrypto/Crypt::encryptString
            $table->string('account_holder');
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->foreign('vendor_id')
                  ->references('id')->on('procurement.vendors')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement.vendor_bank_accounts');
        Schema::dropIfExists('procurement.vendor_addresses');
        Schema::dropIfExists('procurement.vendors');
    }
};
