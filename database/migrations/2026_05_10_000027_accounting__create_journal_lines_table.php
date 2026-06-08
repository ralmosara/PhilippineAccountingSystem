<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * accounting.journal_lines — detail lines of journal_entries.
 *
 * Constraints:
 *   - At most one of {debit, credit} is non-zero per line (CHECK)
 *   - Amounts are NUMERIC(18,4) for foreign currency, with php_amount NUMERIC(18,2)
 *     for the PHP-converted value (locked at posting via fx_rate)
 *   - DEFERRED constraint trigger asserts SUM(debit) = SUM(credit) per entry,
 *     evaluated at COMMIT
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting.journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->smallInteger('line_no');
            $table->uuid('account_id');
            $table->char('currency', 3)->default('PHP');
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->decimal('fx_rate', 18, 8)->default(1);
            $table->decimal('php_amount', 18, 2);                // signed: positive=debit, negative=credit
            $table->uuid('tax_code_id')->nullable();             // logical ref to tax.tax_codes
            $table->uuid('cost_center_id')->nullable();
            $table->uuid('project_id')->nullable();              // logical ref to projects.projects
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('journal_entry_id')
                  ->references('id')->on('accounting.journal_entries')
                  ->onDelete('cascade');

            $table->foreign('account_id')
                  ->references('id')->on('accounting.accounts')
                  ->onDelete('restrict');

            $table->foreign('cost_center_id')
                  ->references('id')->on('accounting.cost_centers')
                  ->onDelete('restrict');

            $table->unique(['journal_entry_id', 'line_no']);
            $table->index(['account_id', 'created_at']);
            $table->index('tax_code_id');
            $table->index('project_id');
        });

        // CHECK: at most one of {debit, credit} non-zero
        DB::unprepared(<<<'SQL'
            ALTER TABLE accounting.journal_lines
            ADD CONSTRAINT one_side_only
            CHECK (
                debit  >= 0
                AND credit >= 0
                AND NOT (debit > 0 AND credit > 0)
            );
        SQL);

        // Deferred balance constraint — evaluated at COMMIT.
        // Allows in-flight unbalanced inserts within a transaction.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting.assert_journal_balanced()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                _entry_id uuid;
                _diff numeric(18, 2);
                _is_posted boolean;
            BEGIN
                _entry_id := COALESCE(NEW.journal_entry_id, OLD.journal_entry_id);

                -- Skip the check until the entry is posted; drafts may be unbalanced
                SELECT (posted_at IS NOT NULL) INTO _is_posted
                  FROM accounting.journal_entries
                 WHERE id = _entry_id;

                IF NOT _is_posted THEN
                    RETURN NULL;
                END IF;

                SELECT COALESCE(SUM(php_amount) FILTER (WHERE debit > 0), 0)
                     - COALESCE(SUM(php_amount) FILTER (WHERE credit > 0), 0)
                  INTO _diff
                  FROM accounting.journal_lines
                 WHERE journal_entry_id = _entry_id;

                IF _diff <> 0 THEN
                    RAISE EXCEPTION 'Journal entry % is not balanced (diff = %); debits must equal credits',
                        _entry_id, _diff USING ERRCODE = 'P0001';
                END IF;

                RETURN NULL;
            END $$;

            CREATE CONSTRAINT TRIGGER journal_must_balance_at_commit
                AFTER INSERT OR UPDATE OR DELETE
                ON accounting.journal_lines
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW
                EXECUTE FUNCTION accounting.assert_journal_balanced();
        SQL);

        // Note: actual audit event emission happens in the Application
        // layer via AuditWriterContract because it carries richer
        // payloads than a trigger could.
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS journal_must_balance_at_commit ON accounting.journal_lines');
        DB::unprepared('DROP FUNCTION IF EXISTS accounting.assert_journal_balanced()');
        Schema::dropIfExists('accounting.journal_lines');
    }
};
