<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * accounting.journal_entries — header table for double-entry transactions.
 *
 * Posted entries are immutable: once posted_at is set, only `reversed_by`
 * may be updated (linking the reversal entry). DELETE is REVOKED in the
 * final lock migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting.journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('fiscal_period_id');
            $table->uuid('document_series_id');
            $table->bigInteger('sequence_no');                  // allocated via allocate_doc_no()
            $table->string('doc_no', 64);                       // 'JV-2026-000001'
            $table->date('entry_date');
            $table->text('memo')->nullable();
            $table->enum('source', [
                'manual', 'sales', 'purchase', 'payroll', 'cash_receipt',
                'cash_disbursement', 'recurring', 'reversal', 'year_end',
            ])->default('manual');
            $table->uuid('source_doc_id')->nullable();          // polymorphic
            $table->string('source_doc_type', 64)->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->uuid('reversed_by')->nullable();             // self-FK to reversal entry
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->foreign('fiscal_period_id')
                  ->references('id')->on('accounting.fiscal_periods')
                  ->onDelete('restrict');

            $table->foreign('document_series_id')
                  ->references('id')->on('accounting.document_series')
                  ->onDelete('restrict');

            $table->unique(['document_series_id', 'sequence_no']);
            $table->unique(['company_id', 'doc_no']);
            $table->index(['fiscal_period_id', 'entry_date']);
            $table->index(['source', 'source_doc_id']);
            $table->index(['posted_at', 'entry_date']);
        });

        Schema::table('accounting.journal_entries', function (Blueprint $table) {
            $table->foreign('reversed_by')
                  ->references('id')->on('accounting.journal_entries')
                  ->onDelete('restrict');
        });

        // Period-lock enforcement on writes
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER reject_locked_journal_writes
                BEFORE INSERT OR UPDATE OF fiscal_period_id, posted_at
                ON accounting.journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION accounting.reject_locked_period_writes();
        SQL);

        // Block UPDATE of posted entries (only reversed_by may change)
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting.reject_posted_entry_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF OLD.posted_at IS NOT NULL THEN
                    -- Allow only the reversed_by linkage update
                    IF NEW.posted_at IS DISTINCT FROM OLD.posted_at
                       OR NEW.entry_date IS DISTINCT FROM OLD.entry_date
                       OR NEW.memo IS DISTINCT FROM OLD.memo
                       OR NEW.source_doc_id IS DISTINCT FROM OLD.source_doc_id
                       OR NEW.fiscal_period_id IS DISTINCT FROM OLD.fiscal_period_id
                    THEN
                        RAISE EXCEPTION 'Posted journal entry % cannot be modified (BIR CAS); use a reversal entry',
                            OLD.id USING ERRCODE = 'P0001';
                    END IF;
                END IF;
                RETURN NEW;
            END $$;

            CREATE TRIGGER reject_posted_journal_mutation
                BEFORE UPDATE ON accounting.journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION accounting.reject_posted_entry_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS reject_posted_journal_mutation ON accounting.journal_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS reject_locked_journal_writes ON accounting.journal_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS accounting.reject_posted_entry_mutation()');
        Schema::dropIfExists('accounting.journal_entries');
    }
};
