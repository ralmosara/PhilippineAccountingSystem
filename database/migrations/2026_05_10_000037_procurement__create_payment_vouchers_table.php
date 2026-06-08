<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement.payment_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('document_series_id');                       // 'CV' series
            $table->bigInteger('sequence_no');
            $table->string('cv_no', 64);
            $table->date('payment_date');
            $table->enum('payment_method', ['cash', 'check', 'bank_transfer', 'wire']);
            $table->string('reference_no', 128)->nullable();          // check no, txn id
            $table->char('currency', 3)->default('PHP');
            $table->decimal('fx_rate',     18, 8)->default(1);
            $table->decimal('total_amount',18, 2);
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->unique(['document_series_id', 'sequence_no']);
            $table->unique(['company_id', 'cv_no']);
            $table->index('payment_date');
        });

        Schema::create('procurement.payment_voucher_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_voucher_id');
            $table->uuid('vendor_bill_id');
            $table->decimal('applied_amount', 18, 2);
            $table->timestampsTz();

            $table->foreign('payment_voucher_id')
                  ->references('id')->on('procurement.payment_vouchers')
                  ->onDelete('cascade');

            $table->foreign('vendor_bill_id')
                  ->references('id')->on('procurement.vendor_bills')
                  ->onDelete('restrict');

            $table->index('vendor_bill_id');
        });

        DB::unprepared('REVOKE DELETE ON procurement.payment_vouchers FROM PUBLIC');
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement.payment_voucher_lines');
        Schema::dropIfExists('procurement.payment_vouchers');
    }
};
