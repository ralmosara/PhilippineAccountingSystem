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
        // Create the loan_type enum; silently skip if it already exists.
        try {
            DB::unprepared("CREATE TYPE payroll.loan_type AS ENUM ('sss_salary_loan', 'hdmf_mpl', 'hdmf_housing')");
        } catch (\Throwable) {
            // Type already exists — safe to continue.
        }

        Schema::create('payroll.loan_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('employee_id');   // logical ref — no FK across schemas
            $table->string('loan_type', 32);   // stores the payroll.loan_type enum value
            $table->string('loan_reference', 50);
            $table->decimal('original_amount',       18, 2);
            $table->decimal('outstanding_balance',   18, 2);
            $table->decimal('monthly_amortization',  18, 2);
            $table->date('started_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'is_active']);
        });

        // Apply the real PostgreSQL enum type via a raw ALTER so Eloquent's
        // string column is backed by the proper enum constraint in the DB.
        DB::unprepared(
            "ALTER TABLE payroll.loan_deductions
             ALTER COLUMN loan_type TYPE payroll.loan_type USING loan_type::payroll.loan_type"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll.loan_deductions');

        try {
            DB::unprepared('DROP TYPE IF EXISTS payroll.loan_type');
        } catch (\Throwable) {
            // May still be referenced elsewhere; leave it.
        }
    }
};
