<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create `assets.depreciation_schedules` — one summary row per asset showing
 * the computed schedule metadata (total periods, remaining periods, next
 * depreciation date). The actual per-period entries are in depreciation_entries.
 *
 * This table is maintained by triggers/application logic on insert to entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema already created by migration 000075
        Schema::create('assets.depreciation_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fixed_asset_id')->unique();  // one schedule per asset

            $table->smallInteger('total_periods');      // = useful_life_months
            $table->smallInteger('periods_posted')->default(0);
            $table->smallInteger('periods_remaining');

            $table->decimal('total_depreciable_amount', 18, 2);  // cost - salvage
            $table->decimal('total_posted', 18, 2)->default(0);
            $table->decimal('remaining_to_post', 18, 2);

            $table->date('schedule_start_date');        // acquisition_date
            $table->date('schedule_end_date');          // start + useful_life_months
            $table->date('next_depreciation_date')->nullable();

            $table->timestampsTz();

            // Logical FK — no cross-schema constraint
            // $table->foreign('fixed_asset_id')->references('id')->on('assets.fixed_assets');
        });

        DB::unprepared("COMMENT ON TABLE assets.depreciation_schedules IS 'Per-asset depreciation schedule summary. Updated by application on each depreciation run.'");
    }

    public function down(): void
    {
        Schema::dropIfExists('assets.depreciation_schedules');
    }
};
