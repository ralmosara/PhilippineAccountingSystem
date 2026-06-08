<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.atc_codes — BIR Alphanumeric Tax Code reference table.
 *
 * Used by the WithholdingTaxCalculator to determine the rate to apply on
 * a vendor bill, and by Form 2307 / SAWT / 1604-CF/E generation.
 *
 * Seeded from BIR-published rate tables (RR 2-98, RR 11-2018, RR 11-2025).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.atc_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tax_code_id')->nullable();                  // optional FK back to tax.tax_codes
            $table->string('code', 16);                               // 'WC010', 'WI011', ...
            $table->string('description');
            $table->decimal('rate', 8, 4);                            // 0.0500 = 5%
            $table->enum('kind', [
                'expanded',         // creditable WT (1601-EQ)
                'final',            // final WT (1601-FQ)
                'compensation',     // 1601-C
                'government',       // government money payments
                'fringe_benefit',   // 1603 fringe benefits tax
                'vat_withheld',     // 5% gov VAT withholding (WV010/WV020)
            ]);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique('code');
            $table->index(['kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.atc_codes');
    }
};
