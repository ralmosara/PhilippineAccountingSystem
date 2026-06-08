<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sales.sales_invoices — BIR-mandatory sequential numbering.
 * Voids preserve the number; DELETE is REVOKED in the final lock pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales.sales_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->uuid('sales_order_id')->nullable();
            $table->uuid('document_series_id');                       // logical ref to accounting.document_series
            $table->bigInteger('sequence_no');                        // allocated atomically
            $table->string('doc_no', 64);                             // 'SI-2026-000001'
            $table->enum('doc_kind', ['cash', 'charge'])->default('charge');

            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->decimal('fx_rate', 18, 8)->default(1);

            // Line breakdown rolled up
            $table->decimal('subtotal',              18, 2)->default(0);   // pre-tax, pre-discount
            $table->decimal('vat_exempt_sales',      18, 2)->default(0);
            $table->decimal('vat_zero_rated_sales',  18, 2)->default(0);
            $table->decimal('vatable_sales',         18, 2)->default(0);
            $table->decimal('vat_amount',            18, 2)->default(0);   // 12% of vatable_sales
            $table->decimal('discount_amount',       18, 2)->default(0);
            $table->decimal('senior_pwd_discount',   18, 2)->default(0);   // RA 9994 / RA 10754
            $table->decimal('withheld_vat',          18, 2)->default(0);   // 5% for gov customers
            $table->decimal('total',                 18, 2)->default(0);
            $table->decimal('php_total',             18, 2)->default(0);   // settled at posting

            $table->timestampTz('posted_at')->nullable();
            $table->uuid('journal_entry_id')->nullable();             // logical ref to accounting.journal_entries
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->timestampsTz();

            $table->foreign('customer_id')
                  ->references('id')->on('sales.customers')
                  ->onDelete('restrict');

            $table->unique(['document_series_id', 'sequence_no']);
            $table->unique(['company_id', 'doc_no']);
            $table->index(['customer_id', 'invoice_date']);
            $table->index(['company_id', 'posted_at']);
            $table->index('voided_at');
        });

        // No DELETE on sales_invoices (BIR-required immutability)
        DB::unprepared('REVOKE DELETE ON sales.sales_invoices FROM PUBLIC');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales.sales_invoices');
    }
};
