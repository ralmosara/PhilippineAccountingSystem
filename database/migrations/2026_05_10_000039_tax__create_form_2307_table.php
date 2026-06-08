<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.form_2307 — issued Certificates of Creditable Tax Withheld at Source.
 *
 * One row per vendor-bill withholding event. Aggregated quarterly into the
 * SAWT (Summary Alphalist of Withholding Taxes) submission alongside
 * 1601-EQ / 1601-FQ filings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.form_2307', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_bill_id');                           // logical ref to procurement.vendor_bills
            $table->uuid('vendor_id');                                // logical ref to procurement.vendors
            $table->string('atc_code', 16);
            $table->decimal('income_payment',   18, 2);               // base
            $table->decimal('tax_withheld',     18, 2);
            $table->date('period_from');
            $table->date('period_to');
            $table->string('pdf_path', 500)->nullable();              // MinIO key once rendered
            $table->timestampTz('issued_at');
            $table->uuid('issued_by')->nullable();
            $table->boolean('sent_to_vendor')->default(false);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['vendor_id', 'period_from']);
            $table->index(['atc_code', 'period_from']);
            $table->unique('vendor_bill_id');                          // one 2307 per bill
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.form_2307');
    }
};
