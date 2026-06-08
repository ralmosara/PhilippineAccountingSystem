<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects.timesheet_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('employee_id');                                 // logical ref — no FK across schemas
            $table->date('work_date');
            $table->decimal('hours', 6, 2);
            $table->decimal('billable_rate', 18, 2)->default(0);
            $table->decimal('billable_amount', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_billed')->default(false);
            $table->timestampsTz();
            $table->foreign('project_id')->references('id')->on('projects.projects')->onDelete('restrict');
            $table->index(['project_id', 'work_date']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects.timesheet_entries');
    }
};
