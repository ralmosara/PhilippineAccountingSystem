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
        Schema::create('sales.official_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('sales_invoice_id')->nullable();             // null for advance/general OR
            $table->uuid('customer_id');
            $table->uuid('document_series_id');                       // logical ref
            $table->bigInteger('sequence_no');
            $table->string('doc_no', 64);                             // 'OR-2026-000001'

            $table->date('received_date');
            $table->decimal('amount',     18, 2);
            $table->char('currency', 3)->default('PHP');
            $table->decimal('fx_rate',    18, 8)->default(1);
            $table->decimal('php_amount', 18, 2);

            $table->enum('payment_method', [
                'cash', 'check', 'bank_transfer', 'credit_card', 'gcash', 'maya',
            ])->default('cash');
            $table->string('reference_no', 128)->nullable();          // check no, txn id
            $table->text('remarks')->nullable();

            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->uuid('issued_by')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->timestampsTz();

            $table->foreign('sales_invoice_id')
                  ->references('id')->on('sales.sales_invoices')
                  ->onDelete('restrict');
            $table->foreign('customer_id')
                  ->references('id')->on('sales.customers')
                  ->onDelete('restrict');

            $table->unique(['document_series_id', 'sequence_no']);
            $table->unique(['company_id', 'doc_no']);
            $table->index(['customer_id', 'received_date']);
            $table->index('voided_at');
        });

        DB::unprepared('REVOKE DELETE ON sales.official_receipts FROM PUBLIC');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales.official_receipts');
    }
};
