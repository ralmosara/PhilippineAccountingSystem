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
        DB::unprepared('CREATE SCHEMA IF NOT EXISTS projects');

        Schema::create('projects.projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id')->nullable();                     // logical ref — no FK
            $table->string('code', 32);
            $table->string('name');
            $table->enum('billing_type', ['fixed_price', 'time_and_materials', 'retainer']);
            $table->enum('status', ['draft', 'active', 'on_hold', 'completed', 'cancelled'])->default('draft');
            $table->decimal('contract_value', 18, 2)->nullable();
            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->uuid('wip_account_id')->nullable();
            $table->uuid('revenue_account_id')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['company_id', 'code']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects.projects');
    }
};
