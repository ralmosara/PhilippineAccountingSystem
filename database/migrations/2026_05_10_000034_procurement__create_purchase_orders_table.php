<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement.purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->uuid('document_series_id');                       // logical ref
            $table->bigInteger('sequence_no');
            $table->string('po_no', 64);                              // 'PO-2026-000001'

            $table->date('order_date');
            $table->date('expected_delivery')->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->decimal('fx_rate', 18, 8)->default(1);
            $table->decimal('subtotal',  18, 2)->default(0);
            $table->decimal('vat_amount',18, 2)->default(0);
            $table->decimal('total',     18, 2)->default(0);

            $table->enum('status', ['draft', 'approved', 'sent', 'partial', 'fulfilled', 'cancelled'])
                  ->default('draft');
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->foreign('vendor_id')
                  ->references('id')->on('procurement.vendors')
                  ->onDelete('restrict');

            $table->unique(['document_series_id', 'sequence_no']);
            $table->unique(['company_id', 'po_no']);
            $table->index(['vendor_id', 'order_date']);
            $table->index('status');
        });

        Schema::create('procurement.purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->smallInteger('line_no');
            $table->uuid('item_id')->nullable();                       // logical ref
            $table->string('description');
            $table->decimal('quantity',          18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);   // GRN rollup
            $table->decimal('billed_quantity',   18, 4)->default(0);   // bills rollup
            $table->decimal('unit_price',        18, 4);
            $table->decimal('line_total',        18, 2);
            $table->uuid('expense_account_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->timestampsTz();

            $table->foreign('purchase_order_id')
                  ->references('id')->on('procurement.purchase_orders')
                  ->onDelete('cascade');

            $table->unique(['purchase_order_id', 'line_no']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement.purchase_order_lines');
        Schema::dropIfExists('procurement.purchase_orders');
    }
};
