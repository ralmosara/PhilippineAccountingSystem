<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create `assets.depreciation_entries` — one row per asset per period.
 *
 * Idempotency is enforced by the UNIQUE constraint on (fixed_asset_id, period_year, period_month).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema already created by migration 000075
        Schema::create('assets.depreciation_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fixed_asset_id')->index();

            $table->smallInteger('period_year');
            $table->smallInteger('period_month');   // 1–12

            $table->decimal('depreciation_amount', 18, 2);
            $table->decimal('accumulated_after', 18, 2);
            $table->decimal('book_value_after', 18, 2);

            // Logical reference to accounting.journal_entries (no FK — cross-schema)
            $table->uuid('journal_entry_id')->nullable();

            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();

            // Idempotency: only one entry per asset per month
            $table->unique(['fixed_asset_id', 'period_year', 'period_month'], 'depr_entries_asset_period_unique');
        });

        DB::unprepared("COMMENT ON TABLE assets.depreciation_entries IS 'Per-asset per-period depreciation postings. Idempotent — one row per (asset, year, month).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('assets.depreciation_entries');
    }
};
