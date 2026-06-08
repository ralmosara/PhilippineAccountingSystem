<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Row-level branch scoping. A user only sees data for the branches they
 * are explicitly granted access to. Enforced by a global Eloquent scope
 * applied to every model with a branch_id column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.user_branch_scopes', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('branch_id');
            $table->boolean('can_post')->default(false);
            $table->timestampTz('granted_at')->useCurrent();
            $table->uuid('granted_by')->nullable();

            $table->primary(['user_id', 'branch_id']);

            $table->foreign('user_id')
                  ->references('id')->on('identity.users')
                  ->onDelete('cascade');

            $table->foreign('branch_id')
                  ->references('id')->on('identity.branches')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.user_branch_scopes');
    }
};
