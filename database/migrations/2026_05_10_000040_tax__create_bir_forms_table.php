<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.bir_forms — registry of every generated BIR return.
 * tax.bir_form_lines — line-level data backing the form (computed once,
 *   read many times by the PDF/XML renderers).
 * tax.form_filing_log — audit trail of submission attempts (eBIRForms / EFPS).
 *
 * The same row covers monthly, quarterly, and annual forms — `period_from`
 * and `period_to` indicate the coverage; `quarter` and `month` are populated
 * for forms that need them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.bir_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->enum('form_type', [
                '2550M', '2550Q',                       // VAT
                '1601C', '1601EQ', '1601FQ',           // Withholding (compensation, expanded, final)
                '0619E', '0619F',                       // Monthly remittance forms
                '1701', '1701A', '1702RT', '1702EX',   // Income tax returns
                '2316',                                  // Annual employee comp cert
                '1604CF', '1604E', '1604F',            // Annual alphalists
                '2000-OT',                               // DST
            ]);
            $table->date('period_from');
            $table->date('period_to');
            $table->smallInteger('year');
            $table->smallInteger('quarter')->nullable();    // 1..4 for quarterly
            $table->smallInteger('month')->nullable();      // 1..12 for monthly
            $table->json('data')->nullable();               // computed line values + meta
            $table->string('xml_path', 500)->nullable();    // eBIRForms XML in MinIO
            $table->string('pdf_path', 500)->nullable();
            $table->string('dat_path', 500)->nullable();    // for SAWT/QAP/MAP attachments
            $table->timestampTz('generated_at')->nullable();
            $table->uuid('generated_by')->nullable();
            $table->timestampTz('filed_at')->nullable();
            $table->string('bir_filing_ref', 128)->nullable();
            $table->decimal('tax_due',  18, 2)->default(0);
            $table->decimal('tax_paid', 18, 2)->default(0);
            $table->enum('status', ['draft', 'generated', 'filed', 'paid', 'amended'])->default('draft');
            $table->uuid('replaced_by_id')->nullable();      // links to amendment
            $table->timestampsTz();

            $table->unique(['company_id', 'form_type', 'period_from', 'period_to']);
            $table->index(['company_id', 'status']);
            $table->index(['form_type', 'year']);
        });

        Schema::create('tax.bir_form_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bir_form_id');
            $table->string('line_code', 16);                 // '1A', '2', '4B', etc. — matches BIR form
            $table->string('description');
            $table->decimal('amount', 18, 2)->default(0);
            $table->json('breakdown')->nullable();           // detail rows feeding this line
            $table->timestampsTz();

            $table->foreign('bir_form_id')
                  ->references('id')->on('tax.bir_forms')
                  ->onDelete('cascade');

            $table->unique(['bir_form_id', 'line_code']);
        });

        Schema::create('tax.form_filing_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('bir_form_id');
            $table->timestampTz('attempted_at')->useCurrent();
            $table->enum('channel', ['ebirforms_offline', 'ebirforms_online', 'efps', 'manual']);
            $table->enum('result',  ['pending', 'success', 'failure', 'amended']);
            $table->string('ack_no', 128)->nullable();
            $table->text('response')->nullable();
            $table->text('error')->nullable();

            $table->foreign('bir_form_id')
                  ->references('id')->on('tax.bir_forms')
                  ->onDelete('restrict');

            $table->index(['bir_form_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.form_filing_log');
        Schema::dropIfExists('tax.bir_form_lines');
        Schema::dropIfExists('tax.bir_forms');
    }
};
