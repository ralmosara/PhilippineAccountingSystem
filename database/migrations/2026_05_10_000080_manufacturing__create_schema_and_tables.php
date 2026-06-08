<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing bounded context — creates the `manufacturing` Postgres schema
 * and all four tables:
 *   manufacturing.bills_of_materials
 *   manufacturing.bom_lines
 *   manufacturing.work_orders
 *   manufacturing.production_run_lines
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 0. Schema ────────────────────────────────────────────────────────
        DB::unprepared('CREATE SCHEMA IF NOT EXISTS manufacturing');

        // ── 1. Bills of Materials ────────────────────────────────────────────
        Schema::create('manufacturing.bills_of_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('item_id');                              // logical ref to inventory.items (finished good)
            $table->string('item_name');                          // denormalized for display
            $table->string('code', 32);
            $table->string('name');
            $table->string('version', 16)->default('1.0');
            $table->enum('status', ['draft', 'active', 'superseded'])->default('draft');
            $table->decimal('standard_batch_size', 10, 4)->default(1);   // units produced per BOM run
            $table->decimal('labor_cost_per_batch', 18, 2)->default(0);
            $table->decimal('overhead_cost_per_batch', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index('item_id');
        });

        // ── 2. BOM Lines (components) ────────────────────────────────────────
        Schema::create('manufacturing.bom_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bom_id');
            $table->uuid('component_item_id');                    // logical ref to inventory.items (raw material)
            $table->string('component_name');                     // denormalized
            $table->decimal('quantity_per_batch', 14, 4);
            $table->string('unit_of_measure', 16)->default('pcs');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->foreign('bom_id')
                  ->references('id')->on('manufacturing.bills_of_materials')
                  ->onDelete('cascade');

            $table->index('bom_id');
            $table->index('component_item_id');
        });

        // ── 3. Work Orders ───────────────────────────────────────────────────
        Schema::create('manufacturing.work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('bom_id');
            $table->string('work_order_no', 32)->unique();
            $table->decimal('quantity_to_produce', 10, 4);
            $table->decimal('quantity_produced', 10, 4)->default(0);
            $table->enum('status', ['draft', 'released', 'in_progress', 'completed', 'cancelled'])
                  ->default('draft');
            $table->date('scheduled_start')->nullable();
            $table->date('scheduled_end')->nullable();
            $table->timestampTz('actual_start')->nullable();
            $table->timestampTz('actual_end')->nullable();
            $table->uuid('warehouse_id')->nullable();             // logical ref to inventory.warehouses
            $table->uuid('wip_account_id')->nullable();           // logical ref to accounting.accounts
            $table->uuid('finished_goods_account_id')->nullable();
            $table->uuid('raw_materials_account_id')->nullable();
            $table->decimal('total_material_cost', 18, 2)->default(0);
            $table->decimal('total_labor_cost', 18, 2)->default(0);
            $table->decimal('total_overhead_cost', 18, 2)->default(0);
            $table->decimal('total_production_cost', 18, 2)->default(0);
            $table->uuid('journal_entry_id')->nullable();         // logical ref to accounting.journal_entries
            $table->timestampsTz();

            $table->foreign('bom_id')
                  ->references('id')->on('manufacturing.bills_of_materials')
                  ->onDelete('restrict');

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'work_order_no']);
            $table->index('bom_id');
            $table->index('journal_entry_id');
        });

        // BIR immutability — production data is financial; no physical deletes
        DB::unprepared('REVOKE DELETE ON manufacturing.work_orders FROM PUBLIC');

        // ── 4. Production Run Lines (materials consumed) ─────────────────────
        Schema::create('manufacturing.production_run_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_order_id');
            $table->uuid('component_item_id');
            $table->string('component_name');
            $table->decimal('quantity_required', 14, 4);
            $table->decimal('quantity_consumed', 14, 4)->default(0);
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('total_cost', 18, 2);
            $table->timestampsTz();

            $table->foreign('work_order_id')
                  ->references('id')->on('manufacturing.work_orders')
                  ->onDelete('cascade');

            $table->index('work_order_id');
            $table->index('component_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing.production_run_lines');

        // Re-grant DELETE before dropping so the drop itself doesn't fail
        DB::unprepared('GRANT DELETE ON manufacturing.work_orders TO PUBLIC');
        Schema::dropIfExists('manufacturing.work_orders');

        Schema::dropIfExists('manufacturing.bom_lines');
        Schema::dropIfExists('manufacturing.bills_of_materials');

        DB::unprepared('DROP SCHEMA IF EXISTS manufacturing CASCADE');
    }
};
