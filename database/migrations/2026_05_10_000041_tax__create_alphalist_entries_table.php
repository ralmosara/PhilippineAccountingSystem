<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.alphalist_entries — line-item rows that feed:
 *   - SAWT (Summary Alphalist of Withholding Taxes) — quarterly attachment to 1601-EQ
 *   - QAP (Quarterly Alphalist of Payees)
 *   - MAP (Monthly Alphalist of Payees)
 *   - 1604-CF Schedule 7.1 (annual alphalist of employees)
 *   - 1604-E (annual alphalist of payees subject to EWT)
 *
 * The DAT (BIR fixed-width text format) exporter reads from this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.alphalist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bir_form_id');
            $table->enum('schedule', [
                '1', '2', '3', '4', '5', '6',     // 1604-E schedules
                '7_1', '7_2', '7_3', '7_4',       // 1604-CF Schedule 7.* sub-types
                'sawt', 'qap', 'map',
            ]);
            $table->string('tin', 32);
            $table->string('registered_name');
            $table->string('atc_code', 16)->nullable();
            $table->decimal('nature_of_payment', 18, 2)->default(0);
            $table->decimal('income_payment',    18, 2);
            $table->decimal('tax_withheld',      18, 2);
            $table->char('tax_type', 1)->nullable();             // 'I'ndividual | 'F'inal | 'C'orporate
            $table->date('payment_date')->nullable();
            $table->json('source_refs')->nullable();              // references to form_2307 ids, vendor_bill ids
            $table->timestampsTz();

            $table->foreign('bir_form_id')
                  ->references('id')->on('tax.bir_forms')
                  ->onDelete('cascade');

            $table->index(['bir_form_id', 'schedule']);
            $table->index('tin');
            $table->index('atc_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.alphalist_entries');
    }
};
