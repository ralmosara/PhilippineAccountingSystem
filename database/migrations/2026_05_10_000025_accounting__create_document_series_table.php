<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * accounting.document_series — BIR-mandated sequential numbering.
 *
 * One row per (branch, document_type, prefix). The next_sequence column is
 * advanced atomically by accounting.allocate_doc_no(_series_id) — a SECURITY
 * INVOKER function that uses SELECT … FOR UPDATE to guarantee no gaps under
 * concurrency.
 *
 * Voided documents preserve their numbers (BIR-required); the allocator never
 * recycles. When series_end is reached, exhausted_at is set and a new ATP/ASTRA
 * series must be activated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting.document_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('branch_id');                          // logical ref to identity.branches
            $table->string('document_type', 16);                // JV | CV | CRV | OR | SI | PO
            $table->string('prefix', 32);                       // 'JV-2026-'
            $table->bigInteger('next_sequence')->default(1);
            $table->bigInteger('series_start');                 // BIR-registered range
            $table->bigInteger('series_end');
            $table->string('bir_atp_no', 64)->nullable();       // Authority to Print (legacy)
            $table->date('atp_date')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('exhausted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'document_type', 'prefix']);
            $table->index(['document_type', 'is_active']);
        });

        // Allocator function — atomic, gap-free, refuses past series_end
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting.allocate_doc_no(_series_id uuid)
            RETURNS bigint
            LANGUAGE plpgsql
            AS $$
            DECLARE
                _seq bigint;
                _end bigint;
            BEGIN
                -- Row-lock the series; subsequent callers wait
                SELECT next_sequence, series_end INTO _seq, _end
                  FROM accounting.document_series
                 WHERE id = _series_id
                 FOR UPDATE;

                IF _seq IS NULL THEN
                    RAISE EXCEPTION 'Document series % not found', _series_id;
                END IF;

                IF _seq > _end THEN
                    UPDATE accounting.document_series
                       SET exhausted_at = COALESCE(exhausted_at, now()),
                           is_active = false
                     WHERE id = _series_id;
                    RAISE EXCEPTION 'Document series % exhausted at %; new BIR ATP required',
                        _series_id, _end USING ERRCODE = 'P0001';
                END IF;

                UPDATE accounting.document_series
                   SET next_sequence = _seq + 1
                 WHERE id = _series_id;

                RETURN _seq;
            END $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS accounting.allocate_doc_no(uuid)');
        Schema::dropIfExists('accounting.document_series');
    }
};
