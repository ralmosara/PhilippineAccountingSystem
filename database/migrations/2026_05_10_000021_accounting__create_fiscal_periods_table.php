<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * accounting.fiscal_periods + the period-lock enforcement function.
 *
 * The function `accounting.reject_locked_period_writes()` is created here
 * but ATTACHED to journal_entries (and other postable tables) in their own
 * migrations. This separation lets us add new postable tables later without
 * redefining the function.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting.fiscal_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fiscal_year_id');
            $table->smallInteger('period_number');                  // 1..12
            $table->string('label', 32);                            // 'Jan 2026'
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('locked_at')->nullable();           // null = open
            $table->uuid('locked_by')->nullable();
            $table->text('lock_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('fiscal_year_id')
                  ->references('id')->on('accounting.fiscal_years')
                  ->onDelete('restrict');

            $table->unique(['fiscal_year_id', 'period_number']);
            $table->index(['fiscal_year_id', 'starts_on']);
            $table->index('locked_at');
        });

        // Period-lock enforcement function (used by triggers on postable tables)
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting.reject_locked_period_writes()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE _locked_at timestamptz;
            BEGIN
                IF NEW.fiscal_period_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT fp.locked_at INTO _locked_at
                  FROM accounting.fiscal_periods fp
                 WHERE fp.id = NEW.fiscal_period_id;

                IF _locked_at IS NOT NULL THEN
                    RAISE EXCEPTION 'Fiscal period % is locked since % — writes rejected (BIR CAS RR 9-2009)',
                        NEW.fiscal_period_id, _locked_at
                        USING ERRCODE = 'P0001';
                END IF;

                RETURN NEW;
            END $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS accounting.reject_locked_period_writes() CASCADE');
        Schema::dropIfExists('accounting.fiscal_periods');
    }
};
