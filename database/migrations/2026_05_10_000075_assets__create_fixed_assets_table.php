<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create the `assets` schema and `assets.fixed_assets` table.
 *
 * Bounded context: Fixed Assets
 * Schema: assets (isolated from accounting, payroll, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Create schema — idempotent (other migrations in this schema check the same)
        DB::unprepared('CREATE SCHEMA IF NOT EXISTS assets');

        DB::unprepared("CREATE TYPE assets.asset_category AS ENUM (
            'land',
            'building',
            'equipment',
            'vehicle',
            'furniture',
            'it_equipment',
            'leasehold_improvement'
        )");

        DB::unprepared("CREATE TYPE assets.asset_depreciation_method AS ENUM (
            'straight_line',
            'double_declining_balance'
        )");

        DB::unprepared("CREATE TYPE assets.asset_status AS ENUM (
            'active',
            'disposed',
            'fully_depreciated'
        )");

        Schema::create('assets.fixed_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->uuid('branch_id')->nullable();
            $table->uuid('cost_center_id')->nullable();

            $table->string('asset_no', 32);
            $table->string('name');
            $table->text('description')->nullable();

            // Enums use raw DB column types (Postgres native enums)
            $table->string('category');         // assets.asset_category
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->smallInteger('useful_life_months');
            $table->string('depreciation_method'); // assets.asset_depreciation_method

            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('book_value', 18, 2);

            // Logical references (no FK constraints — cross-schema boundary)
            $table->uuid('asset_account_id')->nullable();
            $table->uuid('accum_depr_account_id')->nullable();
            $table->uuid('depr_expense_account_id')->nullable();

            $table->string('status')->default('active'); // assets.asset_status
            $table->timestampTz('disposed_at')->nullable();
            $table->decimal('disposal_proceeds', 18, 2)->nullable();
            $table->decimal('disposal_gain_loss', 18, 2)->nullable();
            $table->uuid('disposal_journal_entry_id')->nullable();

            $table->timestampsTz();

            // Asset number is unique per company
            $table->unique(['company_id', 'asset_no']);
        });

        // Cast string columns to native enum types.
        // Drop the default on `status` first so PG doesn't try to auto-cast 'active'::text → enum.
        DB::unprepared("
            ALTER TABLE assets.fixed_assets ALTER COLUMN status DROP DEFAULT;
            ALTER TABLE assets.fixed_assets
                ALTER COLUMN category           TYPE assets.asset_category           USING category::assets.asset_category,
                ALTER COLUMN depreciation_method TYPE assets.asset_depreciation_method USING depreciation_method::assets.asset_depreciation_method,
                ALTER COLUMN status             TYPE assets.asset_status             USING status::assets.asset_status;
            ALTER TABLE assets.fixed_assets ALTER COLUMN status SET DEFAULT 'active'::assets.asset_status;
        ");

        DB::unprepared('COMMENT ON TABLE assets.fixed_assets IS \'Fixed asset register — PPE, IT, vehicles, etc. PFRS for SMEs.\' ');
    }

    public function down(): void
    {
        Schema::dropIfExists('assets.fixed_assets');
        DB::unprepared('DROP TYPE IF EXISTS assets.asset_status');
        DB::unprepared('DROP TYPE IF EXISTS assets.asset_depreciation_method');
        DB::unprepared('DROP TYPE IF EXISTS assets.asset_category');
        // Do NOT drop the schema here — other tables may exist
    }
};
