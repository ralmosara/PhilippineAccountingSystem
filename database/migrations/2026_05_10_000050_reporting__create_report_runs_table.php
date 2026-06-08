<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reporting.report_runs — audit trail of every generated financial statement.
 *
 * The report PAYLOAD is stored as jsonb (the rendered line items) so a
 * historical "what did the BS look like on May 31?" question is answerable
 * even if a back-dated journal entry would otherwise shift it.
 *
 * PDF outputs are stored separately in MinIO under
 * `reporting/{company}/{year}/{report_type}/...` with the path on this row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting.report_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->enum('report_type', [
                'trial_balance',
                'balance_sheet',
                'income_statement',
                'cash_flow_statement',
                'equity_statement',
                'general_journal',
                'general_ledger',
                'sales_book',
                'purchases_book',
                'cash_receipts_book',
                'cash_disbursements_book',
            ]);
            $table->date('period_from');
            $table->date('period_to');
            $table->date('as_of_date')->nullable();   // for point-in-time reports (BS, equity)
            $table->json('payload');                  // computed lines + metadata
            $table->string('pdf_path', 500)->nullable();
            $table->string('csv_path', 500)->nullable();
            $table->timestampTz('generated_at')->useCurrent();
            $table->uuid('generated_by')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'report_type', 'period_to']);
            $table->index(['company_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting.report_runs');
    }
};
