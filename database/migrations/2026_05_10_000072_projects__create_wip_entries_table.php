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
        Schema::create('projects.wip_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('journal_entry_id')->nullable();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('total_hours', 10, 2);
            $table->decimal('total_cost', 18, 2);
            $table->decimal('total_billed', 18, 2);
            $table->decimal('recognized_revenue', 18, 2);
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->timestampsTz();
            $table->foreign('project_id')->references('id')->on('projects.projects')->onDelete('restrict');
        });

        DB::unprepared('REVOKE DELETE ON projects.projects FROM PUBLIC');
        DB::unprepared('REVOKE DELETE ON projects.wip_entries FROM PUBLIC');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects.wip_entries');
    }
};
