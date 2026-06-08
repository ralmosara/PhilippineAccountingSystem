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
        Schema::create('identity.users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            // citext column added below; placeholder to keep Blueprint happy
            $table->string('email');
            $table->string('password_hash');
            $table->string('full_name');
            $table->string('employee_no', 32)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('company_id')
                  ->references('id')->on('identity.companies')
                  ->onDelete('restrict');

            $table->index(['company_id', 'is_active']);
        });

        // Promote email column to citext (case-insensitive, unique)
        DB::unprepared('ALTER TABLE identity.users ALTER COLUMN email TYPE citext USING email::citext');
        DB::unprepared('CREATE UNIQUE INDEX users_email_unique ON identity.users (email)');

        // Sessions table for fallback driver (Redis is primary in dev/prod)
        Schema::create('identity.sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->foreign('user_id')
                  ->references('id')->on('identity.users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.sessions');
        Schema::dropIfExists('identity.users');
    }
};
