<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annual Inventory List submissions (RMC 57-2015 / RR 1-2018).
 *
 * Filed annually within 30 days from fiscal year-end (Jan 30 for calendar
 * filers). Lists every item's beginning + receipts + issues + ending qty
 * and value, signed by the authorized officer.
 *
 * Format: CSV + PDF (BIR accepts both via eBIRForms upload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory.inventory_list_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->smallInteger('year');
            $table->date('as_of_date');                          // typically Dec 31
            $table->date('period_from');
            $table->date('period_to');
            $table->integer('item_count');
            $table->decimal('total_value', 18, 2);
            $table->string('csv_path', 500)->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->timestampTz('generated_at');
            $table->uuid('generated_by')->nullable();
            $table->timestampTz('filed_at')->nullable();
            $table->string('bir_filing_ref', 128)->nullable();
            $table->enum('status', ['draft', 'generated', 'filed', 'amended'])->default('generated');
            $table->timestampsTz();

            $table->unique(['company_id', 'year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory.inventory_list_submissions');
    }
};
