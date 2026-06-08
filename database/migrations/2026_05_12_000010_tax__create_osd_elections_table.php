<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tax.osd_elections — the authoritative record of each taxpayer's
 * deduction regime election for a given fiscal year (RR 2-2010,
 * RR 8-2018 § 4 / § 3).
 *
 *   regime ∈ { 'itemized', 'osd', 'flat_8pct' }
 *   - 'itemized'  : default; Allowable Itemized Deductions per ledger
 *   - 'osd'       : 40% of gross sales (1701) / gross income (1702-RT)
 *   - 'flat_8pct' : RA 10963 / TRAIN; individuals only, gross ≤ ₱3M
 *
 * The election is established by the FIRST quarterly ITR filed for the year
 * (or by the annual if no quarterlies preceded it). Once stored, it cannot
 * be amended for the rest of the year — that's why this row carries a
 * locked_at timestamp and the unique key on (company, year, taxpayer_type).
 *
 * `taxpayer_type` lets a hybrid filer who has BOTH an individual sole-prop
 * AND a corporate entity (rare but legal) maintain separate elections.
 *
 * Amendment workflow: rejecting the locked election requires creating a
 * superseding row with `replaces_id` set and a written `reason`. The active
 * partial-unique index allows only one active (replaces_id IS NULL OR
 * superseded_at IS NULL) election per (company, year, taxpayer_type).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.osd_elections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->smallInteger('fiscal_year');
            $table->enum('taxpayer_type', ['individual', 'corporate']);
            $table->enum('regime', ['itemized', 'osd', 'flat_8pct']);

            // What form & quarter declared this election (audit trail)
            $table->string('declared_in_form_type', 16);   // '1701Q','1702Q','1701','1702RT'
            $table->smallInteger('declared_in_quarter')->nullable();
            $table->uuid('declared_in_bir_form_id')->nullable();
            $table->timestampTz('locked_at');
            $table->uuid('locked_by');

            // Amendment chain
            $table->uuid('replaces_id')->nullable();        // points at superseded election
            $table->timestampTz('superseded_at')->nullable();
            $table->text('supersede_reason')->nullable();

            $table->timestampsTz();

            $table->index(['company_id', 'fiscal_year']);
        });

        Schema::table('tax.osd_elections', function (Blueprint $table) {
            $table->foreign('replaces_id')->references('id')->on('tax.osd_elections');
        });

        // Partial unique index: only ONE *active* election (superseded_at IS NULL)
        // per (company, year, taxpayer_type). Superseded rows are allowed to coexist
        // with their successor — that's the whole point of the amendment chain.
        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX osd_elections_active_unique
                ON tax.osd_elections (company_id, fiscal_year, taxpayer_type)
                WHERE superseded_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.osd_elections');
    }
};
