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
        // UOM (Units of Measure) — seeded global
        Schema::create('inventory.units_of_measure', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 16);                          // 'pc' | 'kg' | 'm' | 'L' | 'hr'
            $table->string('name');
            $table->enum('category', ['count', 'weight', 'length', 'volume', 'time']);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique('code');
        });

        // Item categories — hierarchical via ltree
        Schema::create('inventory.item_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->uuid('parent_id')->nullable();
            $table->string('path');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('inventory.item_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('id')->on('inventory.item_categories')
                  ->onDelete('restrict');
        });

        DB::unprepared('ALTER TABLE inventory.item_categories ALTER COLUMN path TYPE ltree USING path::ltree');
        DB::unprepared('CREATE INDEX item_categories_path_gist ON inventory.item_categories USING gist (path)');

        // Items
        Schema::create('inventory.items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('category_id')->nullable();
            $table->string('sku', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('kind', ['stock', 'service', 'asset', 'raw_material', 'fg', 'wip'])->default('stock');
            $table->uuid('uom_id');
            $table->enum('costing_method', ['moving_average', 'fifo', 'standard'])->default('moving_average');
            $table->decimal('moving_avg_cost', 18, 4)->default(0);
            $table->decimal('standard_cost',   18, 4)->nullable();
            $table->decimal('selling_price',   18, 4)->default(0);
            $table->boolean('is_vatable')->default(true);
            $table->boolean('is_inventory')->default(true);          // false for services
            $table->boolean('track_lots')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->decimal('weight_kg', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('category_id')
                  ->references('id')->on('inventory.item_categories')
                  ->onDelete('restrict');

            $table->foreign('uom_id')
                  ->references('id')->on('inventory.units_of_measure')
                  ->onDelete('restrict');

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'is_active', 'kind']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory.items');
        Schema::dropIfExists('inventory.item_categories');
        Schema::dropIfExists('inventory.units_of_measure');
    }
};
