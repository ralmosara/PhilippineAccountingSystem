<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.eis_submissions — one row per sales invoice transmitted to BIR EIS.
 * RR 8-2022, RR 6-2024.
 *
 * The Sales module emits InvoiceIssued; a listener inserts the row in
 * status='pending'. A Horizon job (TransmitInvoiceToEisJob) signs the
 * payload, posts to BIR, and updates status to 'acknowledged' or 'rejected'.
 * Failed attempts go to tax.eis_retries with exponential backoff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.eis_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sales_invoice_id');                         // logical ref to sales.sales_invoices
            $table->uuid('bir_certificate_id')->nullable();           // X.509 cert used to sign
            $table->json('payload');                                  // canonical BIR EIS JSON
            $table->binary('signature')->nullable();                  // PKCS#7 detached signature
            $table->string('qr_url', 500)->nullable();                // BIR portal URL embedded in QR
            $table->string('qr_image_path', 500)->nullable();         // MinIO key
            $table->string('bir_ack_no', 128)->nullable();
            $table->enum('status', [
                'pending', 'submitting', 'acknowledged', 'rejected', 'failed',
            ])->default('pending');
            $table->smallInteger('retry_count')->default(0);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();

            $table->unique('sales_invoice_id');                        // one EIS submission per invoice
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('tax.eis_retries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('eis_submission_id');
            $table->timestampTz('attempted_at');
            $table->smallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();

            $table->foreign('eis_submission_id')
                  ->references('id')->on('tax.eis_submissions')
                  ->onDelete('cascade');

            $table->index(['eis_submission_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.eis_retries');
        Schema::dropIfExists('tax.eis_submissions');
    }
};
