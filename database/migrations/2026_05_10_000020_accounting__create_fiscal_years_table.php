<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting.fiscal_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');           // logical ref to identity.companies (no cross-schema FK)
            $table->smallInteger('year_number');  // 2026
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('closed_at')->nullable();   // year-end close
            $table->uuid('closed_by')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'year_number']);
            $table->index(['company_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.fiscal_years');
    }
};
