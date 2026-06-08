<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie Permission tables, placed in the `identity` schema (configured in
 * config/permission.php). UUID primary keys to match the project convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableNames  = config('permission.table_names');
        $columnNames = config('permission.column_names');

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->string('module')->nullable();         // e.g. 'accounting'
            $table->string('display_name')->nullable();
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
            $table->index('module');
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->string('display_name')->nullable();
            $table->boolean('is_system')->default(false); // can't be deleted via UI
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->uuid('permission_id');
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);

            $table->foreign('permission_id')
                  ->references('id')->on($tableNames['permissions'])
                  ->onDelete('cascade');

            $table->primary(
                ['permission_id', $columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_pk'
            );
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhp_model_id_model_type_index');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->uuid('role_id');
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);

            $table->foreign('role_id')
                  ->references('id')->on($tableNames['roles'])
                  ->onDelete('cascade');

            $table->primary(
                ['role_id', $columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_pk'
            );
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhr_model_id_model_type_index');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->uuid('permission_id');
            $table->uuid('role_id');

            $table->foreign('permission_id')
                  ->references('id')->on($tableNames['permissions'])
                  ->onDelete('cascade');

            $table->foreign('role_id')
                  ->references('id')->on($tableNames['roles'])
                  ->onDelete('cascade');

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_pk');
        });

        // Bust the cache so newly inserted roles/permissions are visible
        app('cache')
            ->store(config('permission.cache.store') !== 'default'
                ? config('permission.cache.store')
                : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
