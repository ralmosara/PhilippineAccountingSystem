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
        Schema::create('accounting.cost_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->uuid('parent_id')->nullable();
            $table->string('path');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('accounting.cost_centers', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('id')->on('accounting.cost_centers')
                  ->onDelete('restrict');
        });

        DB::unprepared('ALTER TABLE accounting.cost_centers ALTER COLUMN path TYPE ltree USING path::ltree');
        DB::unprepared('CREATE INDEX cost_centers_path_gist ON accounting.cost_centers USING gist (path)');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.cost_centers');
    }
};
