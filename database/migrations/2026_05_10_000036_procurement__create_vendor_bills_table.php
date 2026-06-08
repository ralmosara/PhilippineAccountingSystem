<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement.vendor_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->uuid('purchase_order_id')->nullable();             // null for non-PO bills
            $table->string('vendor_invoice_no', 64);                   // supplier's SI/OR number
            $table->date('vendor_invoice_date');
            $table->date('bill_date');                                 // received date
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->decimal('fx_rate', 18, 8)->default(1);

            $table->decimal('subtotal',           18, 2)->default(0);
            $table->decimal('vat_input',          18, 2)->default(0);  // creditable input VAT
            $table->decimal('vat_input_deferred', 18, 2)->default(0);  // capital goods >= 1M, 60-mo amort
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->string('withholding_atc_code', 16)->nullable();
            $table->decimal('withholding_rate',   8, 4)->nullable();
            $table->decimal('total',              18, 2)->default(0);
            $table->decimal('php_total',          18, 2)->default(0);

            $table->timestampTz('posted_at')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->timestampTz('three_way_matched_at')->nullable();
            $table->enum('match_status', ['unmatched', 'matched', 'variance'])->default('unmatched');
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('vendor_id')
                  ->references('id')->on('procurement.vendors')
                  ->onDelete('restrict');

            $table->foreign('purchase_order_id')
                  ->references('id')->on('procurement.purchase_orders')
                  ->onDelete('restrict');

            $table->unique(['vendor_id', 'vendor_invoice_no']);
            $table->index(['company_id', 'bill_date']);
            $table->index('match_status');
            $table->index('posted_at');
            $table->index('voided_at');
        });

        Schema::create('procurement.vendor_bill_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_bill_id');
            $table->smallInteger('line_no');
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('item_id')->nullable();
            $table->string('description');
            $table->decimal('quantity',     18, 4);
            $table->decimal('unit_price',   18, 4);
            $table->decimal('vat_amount',   18, 2)->default(0);
            $table->decimal('line_total',   18, 2);
            $table->uuid('expense_account_id')->nullable();
            $table->uuid('tax_code_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->timestampsTz();

            $table->foreign('vendor_bill_id')
                  ->references('id')->on('procurement.vendor_bills')
                  ->onDelete('cascade');

            $table->unique(['vendor_bill_id', 'line_no']);
        });

        DB::unprepared('REVOKE DELETE ON procurement.vendor_bills FROM PUBLIC');
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement.vendor_bill_lines');
        Schema::dropIfExists('procurement.vendor_bills');
    }
};
