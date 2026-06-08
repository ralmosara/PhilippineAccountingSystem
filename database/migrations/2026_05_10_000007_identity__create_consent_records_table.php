<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data Privacy Act (RA 10173) consent records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.consent_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('consent_type');                  // data_processing | marketing | analytics
            $table->boolean('granted');
            $table->text('purpose')->nullable();
            $table->timestampTz('recorded_at')->useCurrent();
            $table->ipAddress('ip_address')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')
                  ->references('id')->on('identity.users')
                  ->onDelete('cascade');

            $table->index(['user_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.consent_records');
    }
};
