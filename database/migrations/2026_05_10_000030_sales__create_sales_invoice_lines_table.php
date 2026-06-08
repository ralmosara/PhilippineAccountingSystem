<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales.sales_invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sales_invoice_id');
            $table->smallInteger('line_no');
            $table->uuid('item_id')->nullable();                     // logical ref to inventory.items
            $table->string('description');
            $table->decimal('quantity',         18, 4)->default(1);
            $table->decimal('unit_price',       18, 4);
            $table->decimal('discount_pct',     5,  2)->default(0);
            $table->decimal('discount_amount',  18, 2)->default(0);
            $table->uuid('tax_code_id')->nullable();                 // logical ref to tax.tax_codes
            $table->decimal('vat_amount',       18, 2)->default(0);
            $table->decimal('line_total',       18, 2);              // net of discount, incl. VAT
            $table->uuid('revenue_account_id')->nullable();          // logical ref to accounting.accounts
            $table->uuid('project_id')->nullable();
            $table->timestampsTz();

            $table->foreign('sales_invoice_id')
                  ->references('id')->on('sales.sales_invoices')
                  ->onDelete('cascade');

            $table->unique(['sales_invoice_id', 'line_no']);
            $table->index('item_id');
            $table->index('tax_code_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales.sales_invoice_lines');
    }
};
