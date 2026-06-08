<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security events — login attempts, MFA failures, permission denials, etc.
 *
 * Not hash-chained (different audit profile from financial events) but kept
 * alongside audit.events for unified investigation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit.security_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestampTz('occurred_at')->useCurrent();
            $table->uuid('user_id')->nullable();
            $table->string('event_type');     // login_success, login_failure, mfa_failure, ...
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit.security_events');
    }
};
