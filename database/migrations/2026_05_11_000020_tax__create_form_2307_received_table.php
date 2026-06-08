<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.form_2307_received — Certificates of Creditable Tax Withheld at Source
 * that OUR CUSTOMERS issue to us when they withhold income tax on their
 * payments to our company.
 *
 *   Issued ─►   our company → vendor   (tax.form_2307)
 *   Received ◄─ customer    → our company   (tax.form_2307_received)  ← this table
 *
 * BIR significance: the SUM of `tax_withheld` for a fiscal year goes on
 * Form 1701 line 60(D) / 1702-RT line 30 as a **tax credit**, reducing the
 * income tax payable peso-for-peso. Without these we'd overpay BIR by
 * exactly the amount our clients already remitted on our behalf.
 *
 * Quarterly: these feed the SAWT attachment to 1701-Q / 1702-Q.
 * Annually:  they feed the 1701/1702 alphalist of payors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.form_2307_received', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');                                // recipient (us)

            // The payor (our customer) — BIR identifies them by TIN + reg name.
            // We don't FK to sales.customers because:
            //   (a) the cert may come from a payor who is not in our customer table yet
            //   (b) RR 11-2018 requires the certificate to be filed verbatim
            $table->uuid('customer_id')->nullable();                   // soft link
            $table->string('payor_tin', 16);                           // "000-123-456-000" or "000123456000"
            $table->string('payor_registered_name');
            $table->string('payor_branch_code', 8)->default('000');
            $table->string('payor_address')->nullable();

            // The certificate itself
            $table->string('certificate_no', 64)->nullable();          // payor's running serial; many leave blank
            $table->string('atc_code', 16);                            // e.g. WI010, WC158, WC160
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('income_payment', 18, 2);                  // gross/net depending on ATC
            $table->decimal('tax_withheld',   18, 2);

            // Source artefact
            $table->string('source_pdf_path', 500)->nullable();        // MinIO key for the scanned/uploaded PDF
            $table->enum('entry_method', ['manual', 'pdf_upload', 'csv_import'])->default('manual');

            // Workflow / reconciliation
            $table->enum('status', ['draft', 'recorded', 'claimed', 'rejected'])->default('recorded');
            $table->uuid('claimed_in_bir_form_id')->nullable();        // FK-like to tax.bir_forms (1701/1702/SAWT)
            $table->text('rejection_reason')->nullable();              // BIR audit query, or our reviewer rejected

            // Soft link to the JV that booked the underlying revenue + withholding receivable
            $table->uuid('journal_entry_id')->nullable();              // logical ref to accounting.journal_entries

            $table->uuid('recorded_by');
            $table->timestampsTz();

            // No two identical certs from the same payor for the same period + ATC.
            // (A correction goes in as a new row with a 'rejected' status flag on the prior.)
            $table->unique(['company_id', 'payor_tin', 'period_from', 'period_to', 'atc_code', 'certificate_no'], 'form_2307_received_unique_cert');

            // Hot indexes for the aggregator (claims-for-period query)
            $table->index(['company_id', 'period_from', 'period_to'], 'form_2307_received_period_idx');
            $table->index(['company_id', 'status'],                   'form_2307_received_status_idx');
            $table->index(['company_id', 'atc_code'],                 'form_2307_received_atc_idx');
            $table->index('claimed_in_bir_form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.form_2307_received');
    }
};
