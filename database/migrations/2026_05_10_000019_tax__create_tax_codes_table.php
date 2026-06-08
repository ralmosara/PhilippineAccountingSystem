<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.tax_codes — VAT, percentage tax, and excise codes.
 * ATC (withholding) codes get their own table (tax.atc_codes) in a later migration.
 *
 * Created in this batch because accounting.journal_lines.tax_code_id holds
 * a logical UUID reference to it (no cross-schema FK constraint, per the
 * project's bounded-context rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.tax_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32);                              // VAT-12 | VAT-0 | VAT-EX | PT-3
            $table->string('name');
            $table->decimal('rate', 8, 4)->default(0);               // 0.1200 = 12%
            $table->enum('kind', [
                'vat_output', 'vat_input', 'vat_exempt', 'vat_zero',
                'withholding', 'excise', 'percentage',
            ]);
            $table->text('description')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->uuid('replaced_by_id')->nullable();
            $table->timestampsTz();

            $table->unique('code');
            $table->index(['kind', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.tax_codes');
    }
};
