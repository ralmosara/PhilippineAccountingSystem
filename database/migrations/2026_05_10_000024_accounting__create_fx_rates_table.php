<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting.fx_rates — daily FX rates against PHP, sourced from BSP.
 * Populated by a scheduled job (BSP scrape) and falls back to manual entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting.fx_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('rate_date');
            $table->char('from_ccy', 3);                   // ISO 4217: USD, EUR, JPY, CNY
            $table->char('to_ccy', 3)->default('PHP');
            $table->decimal('rate', 18, 8);                // 56.25000000
            $table->string('source')->default('BSP');      // BSP | manual | override
            $table->timestampTz('fetched_at')->useCurrent();
            $table->timestampsTz();

            $table->unique(['rate_date', 'from_ccy', 'to_ccy']);
            $table->index(['from_ccy', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.fx_rates');
    }
};
