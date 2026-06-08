<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->string('bir_branch_code', 16);     // 00-001 head office, 00-002+ branches
            $table->text('address')->nullable();
            $table->string('telephone', 32)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('company_id')
                  ->references('id')->on('identity.companies')
                  ->onDelete('restrict');

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'bir_branch_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.branches');
    }
};
