<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backup logs (BIR CAS — proof of regular backups required for PTU).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit.backup_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->enum('kind', ['full', 'incremental', 'differential']);
            $table->bigInteger('size_bytes')->nullable();
            $table->string('artifact_path')->nullable();
            $table->binary('sha256_checksum')->nullable();
            $table->enum('status', ['running', 'success', 'failed', 'partial']);
            $table->text('error')->nullable();
            $table->timestampsTz();

            $table->index('started_at');
            $table->index('status');
        });

        Schema::create('audit.backup_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('backup_log_id');
            $table->timestampTz('verified_at');
            $table->boolean('restore_test_passed');
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->foreign('backup_log_id')
                  ->references('id')->on('audit.backup_logs')
                  ->onDelete('restrict');

            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit.backup_verifications');
        Schema::dropIfExists('audit.backup_logs');
    }
};
