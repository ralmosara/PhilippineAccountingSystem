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
        Schema::create('hr.departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->uuid('parent_id')->nullable();
            $table->string('path');                              // promoted to ltree below
            $table->uuid('head_employee_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('hr.departments', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('id')->on('hr.departments')
                  ->onDelete('restrict');
        });

        DB::unprepared('ALTER TABLE hr.departments ALTER COLUMN path TYPE ltree USING path::ltree');
        DB::unprepared('CREATE INDEX departments_path_gist ON hr.departments USING gist (path)');

        Schema::create('hr.positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('title');
            $table->uuid('department_id')->nullable();
            $table->text('description')->nullable();
            $table->decimal('salary_grade_min', 18, 2)->nullable();
            $table->decimal('salary_grade_max', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('department_id')
                  ->references('id')->on('hr.departments')
                  ->onDelete('restrict');

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr.positions');
        Schema::dropIfExists('hr.departments');
    }
};
