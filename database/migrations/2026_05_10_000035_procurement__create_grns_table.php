<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement.goods_receipt_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->string('grn_no', 64);
            $table->date('received_date');
            $table->uuid('warehouse_id')->nullable();                  // logical ref to inventory
            $table->uuid('received_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->foreign('purchase_order_id')
                  ->references('id')->on('procurement.purchase_orders')
                  ->onDelete('restrict');

            $table->unique('grn_no');
            $table->index(['purchase_order_id', 'received_date']);
        });

        Schema::create('procurement.grn_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grn_id');
            $table->uuid('purchase_order_line_id');
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('accepted_quantity', 18, 4);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('grn_id')
                  ->references('id')->on('procurement.goods_receipt_notes')
                  ->onDelete('cascade');

            $table->foreign('purchase_order_line_id')
                  ->references('id')->on('procurement.purchase_order_lines')
                  ->onDelete('restrict');

            $table->index('grn_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement.grn_lines');
        Schema::dropIfExists('procurement.goods_receipt_notes');
    }
};
