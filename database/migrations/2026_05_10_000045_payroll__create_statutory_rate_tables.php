<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statutory rate tables — versioned via effective_from/effective_to.
 * Updated when SSS/PhilHealth/Pag-IBIG/BIR issues a new circular.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SSS — bracket-based MSC table
        Schema::create('payroll.sss_rate_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('msc_floor',   18, 2);
            $table->decimal('msc_ceiling', 18, 2);
            $table->json('brackets');   // [{msc_floor, msc_ceiling, ee_amount, er_amount, ec_amount}, ...]
            $table->timestampsTz();

            $table->index('effective_from');
        });

        // PhilHealth — single rate with floor/ceiling
        Schema::create('payroll.philhealth_rate_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('premium_rate', 8, 4);                   // 0.0500 = 5%
            $table->decimal('salary_floor',   18, 2);                // 10000
            $table->decimal('salary_ceiling', 18, 2);                // 100000
            $table->timestampsTz();
        });

        // Pag-IBIG — two-tier rate
        Schema::create('payroll.pagibig_rate_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('ee_rate_low',  8, 4);                   // 0.0100 for ≤ ₱1,500
            $table->decimal('ee_rate_high', 8, 4);                   // 0.0200 for > ₱1,500
            $table->decimal('er_rate',      8, 4);                   // 0.0200
            $table->decimal('low_threshold',18, 2);                  // 1500
            $table->decimal('salary_cap',   18, 2);                  // 10000
            $table->timestampsTz();
        });

        // BIR withholding tax table (TRAIN Law brackets)
        Schema::create('payroll.bir_tax_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'semimonthly', 'monthly', 'annual']);
            $table->json('brackets');   // [{floor, ceiling, base_tax, rate}, ...]
            $table->timestampsTz();

            $table->unique(['frequency', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll.bir_tax_tables');
        Schema::dropIfExists('payroll.pagibig_rate_tables');
        Schema::dropIfExists('payroll.philhealth_rate_tables');
        Schema::dropIfExists('payroll.sss_rate_tables');
    }
};
