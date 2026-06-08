<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.mfa_secrets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('secret_encrypted');                  // pgp_sym_encrypt
            $table->json('recovery_codes_encrypted')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')
                  ->references('id')->on('identity.users')
                  ->onDelete('cascade');

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.mfa_secrets');
    }
};
