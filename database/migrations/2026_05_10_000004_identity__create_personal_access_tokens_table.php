<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum's personal access tokens — placed in identity schema rather than
 * the default public schema to keep all auth concerns co-located.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.personal_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.personal_access_tokens');
    }
};
