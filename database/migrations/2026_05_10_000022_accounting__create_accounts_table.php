<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * accounting.accounts — Chart of Accounts.
 * Hierarchical via ltree path; PFRS-aligned classifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting.accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);                              // 1100-01-001
            $table->string('name');
            $table->enum('type', [
                'asset', 'liability', 'equity', 'revenue', 'expense',
                'contra_asset', 'contra_liability', 'contra_equity',
            ]);
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->uuid('parent_id')->nullable();
            $table->string('path');                                  // promoted to ltree below
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->string('pfrs_classification')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'is_active', 'is_postable']);
        });

        Schema::table('accounting.accounts', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('id')->on('accounting.accounts')
                  ->onDelete('restrict');
        });

        // Promote path to ltree (Laravel's Blueprint doesn't have a native type)
        DB::unprepared('ALTER TABLE accounting.accounts ALTER COLUMN path TYPE ltree USING path::ltree');
        DB::unprepared('CREATE INDEX accounts_path_gist ON accounting.accounts USING gist (path)');
        DB::unprepared('CREATE INDEX accounts_path_btree ON accounting.accounts (path)');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.accounts');
    }
};
