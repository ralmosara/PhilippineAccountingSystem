<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hr.employees — government IDs are encrypted via pgcrypto/Crypt::encryptString.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr.employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();              // logical ref to identity.branches
            $table->uuid('user_id')->nullable();                // link to identity.users (may be null)
            $table->string('employee_no', 32);

            // PII — encrypted columns
            $table->text('tin_encrypted')->nullable();
            $table->text('sss_no_encrypted')->nullable();
            $table->text('philhealth_no_encrypted')->nullable();
            $table->text('pagibig_no_encrypted')->nullable();

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 16)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated', 'divorced'])->default('single');
            $table->string('nationality', 64)->default('Filipino');

            $table->text('address_line')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('province', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();

            $table->date('hired_on');
            $table->date('regularized_on')->nullable();
            $table->date('separated_on')->nullable();
            $table->enum('separation_reason', ['resigned', 'terminated', 'retired', 'deceased', 'end_of_contract'])->nullable();

            $table->uuid('department_id')->nullable();
            $table->uuid('position_id')->nullable();
            $table->uuid('immediate_supervisor_id')->nullable();
            $table->enum('employment_status', ['probationary', 'regular', 'contract', 'project', 'consultant'])->default('probationary');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('department_id')
                  ->references('id')->on('hr.departments')
                  ->onDelete('restrict');

            $table->foreign('position_id')
                  ->references('id')->on('hr.positions')
                  ->onDelete('restrict');

            $table->unique(['company_id', 'employee_no']);
            $table->index(['company_id', 'is_active']);
            $table->index('department_id');
            $table->index('separated_on');
        });

        Schema::table('hr.employees', function (Blueprint $table) {
            $table->foreign('immediate_supervisor_id')
                  ->references('id')->on('hr.employees')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr.employees');
    }
};
