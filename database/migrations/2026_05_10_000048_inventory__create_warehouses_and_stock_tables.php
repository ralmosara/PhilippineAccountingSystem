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
        // Warehouses
        Schema::create('inventory.warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();              // logical ref to identity.branches
            $table->string('code', 32);
            $table->string('name');
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        // Stock balances — materialized current balance per (item × warehouse)
        Schema::create('inventory.stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('value',    18, 2)->default(0);
            $table->timestampTz('last_movement_at')->nullable();
            $table->timestampsTz();

            $table->foreign('item_id')
                  ->references('id')->on('inventory.items')
                  ->onDelete('restrict');

            $table->foreign('warehouse_id')
                  ->references('id')->on('inventory.warehouses')
                  ->onDelete('restrict');

            $table->unique(['item_id', 'warehouse_id']);
            $table->index('item_id');
            $table->index('warehouse_id');
        });

        // Negative stock guard (override via permission `inventory.allow_negative_stock`
        // — bypassed by setting GUC inventory.allow_negative inside a transaction)
        DB::unprepared(<<<'SQL'
            ALTER TABLE inventory.stock_balances
            ADD CONSTRAINT no_negative_stock
            CHECK (quantity >= 0);
        SQL);

        // Stock movements — append-only journal of every stock change
        Schema::create('inventory.stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->enum('movement_type', [
                'receipt',         // purchase / GRN
                'issue',           // sale / consumption
                'transfer_in',
                'transfer_out',
                'adjustment',      // positive or negative manual adjustment
                'production',      // FG output
                'consumption',     // raw material used in production
                'return',          // sales/customer return
            ]);
            $table->decimal('quantity',   18, 4);             // signed: + for inbound, − for outbound
            $table->decimal('unit_cost',  18, 4);
            $table->decimal('total_cost', 18, 2);
            $table->uuid('source_doc_id')->nullable();        // polymorphic
            $table->string('source_doc_type', 64)->nullable(); // 'SalesInvoice' | 'VendorBill' | 'Adjustment'
            $table->uuid('project_id')->nullable();
            $table->timestampTz('moved_at')->useCurrent();
            $table->uuid('moved_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->foreign('item_id')
                  ->references('id')->on('inventory.items')
                  ->onDelete('restrict');

            $table->foreign('warehouse_id')
                  ->references('id')->on('inventory.warehouses')
                  ->onDelete('restrict');

            $table->index(['item_id', 'moved_at']);
            $table->index(['warehouse_id', 'moved_at']);
            $table->index(['source_doc_id', 'source_doc_type']);
            $table->index('moved_at');
        });

        // Costing history — snapshot of moving avg after each receipt
        Schema::create('inventory.costing_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->timestampTz('effective_at');
            $table->decimal('moving_avg_cost',  18, 4);
            $table->decimal('quantity_on_hand', 18, 4);
            $table->decimal('value_on_hand',    18, 2);
            $table->uuid('trigger_movement_id')->nullable();
            $table->timestampsTz();

            $table->foreign('item_id')
                  ->references('id')->on('inventory.items')
                  ->onDelete('cascade');

            $table->foreign('trigger_movement_id')
                  ->references('id')->on('inventory.stock_movements')
                  ->onDelete('set null');

            $table->index(['item_id', 'effective_at']);
        });

        // BIR-required immutability on stock movements
        DB::unprepared('REVOKE DELETE ON inventory.stock_movements FROM PUBLIC');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory.costing_history');
        Schema::dropIfExists('inventory.stock_movements');
        Schema::dropIfExists('inventory.stock_balances');
        Schema::dropIfExists('inventory.warehouses');
    }
};
