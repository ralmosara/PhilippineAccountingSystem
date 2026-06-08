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
        // Compensation packages — versioned via effective_from/effective_to
        Schema::create('payroll.compensation_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('basic_monthly',   18, 2);
            $table->decimal('basic_daily',     18, 2);
            $table->smallInteger('working_days_per_month')->default(22);
            $table->smallInteger('hours_per_day')->default(8);
            $table->decimal('hourly_rate',     18, 4);
            $table->boolean('is_minimum_wage_earner')->default(false);   // RA 9504 — MWEs exempt from WT
            $table->timestampsTz();

            $table->foreign('employee_id')
                  ->references('id')->on('hr.employees')
                  ->onDelete('restrict');

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('payroll.compensation_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('compensation_package_id');
            $table->enum('component_type', ['allowance', 'deduction', 'bonus']);
            $table->string('code', 32);                                    // 'TRANSPORT', 'MEAL', 'RICE'
            $table->string('name');
            $table->decimal('amount', 18, 2);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_subject_to_sss')->default(true);
            $table->boolean('is_de_minimis')->default(false);              // RR 5-2011 thresholds
            $table->decimal('de_minimis_cap', 18, 2)->nullable();
            $table->boolean('recurring')->default(true);
            $table->date('one_time_date')->nullable();
            $table->timestampsTz();

            $table->foreign('compensation_package_id')
                  ->references('id')->on('payroll.compensation_packages')
                  ->onDelete('cascade');
        });

        // Payroll periods
        Schema::create('payroll.payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->enum('frequency', ['monthly', 'semimonthly', 'biweekly', 'weekly']);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->boolean('is_finalized')->default(false);
            $table->timestampsTz();

            $table->unique(['company_id', 'period_start', 'period_end']);
        });

        // Payroll runs
        Schema::create('payroll.payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_period_id');
            $table->string('run_no', 64);
            $table->enum('run_type', ['regular', '13th_month', 'final_pay', 'adjustment'])->default('regular');
            $table->timestampTz('computed_at')->nullable();
            $table->uuid('computed_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->uuid('journal_entry_id')->nullable();              // logical ref to accounting.journal_entries
            $table->enum('status', ['draft', 'computed', 'approved', 'paid', 'reversed'])->default('draft');
            $table->timestampsTz();

            $table->foreign('payroll_period_id')
                  ->references('id')->on('payroll.payroll_periods')
                  ->onDelete('restrict');

            $table->unique('run_no');
            $table->index('status');
        });

        // Payslips — one row per (run × employee)
        Schema::create('payroll.payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_run_id');
            $table->uuid('employee_id');
            $table->decimal('gross_compensation',    18, 2)->default(0);
            $table->decimal('taxable_compensation',  18, 2)->default(0);
            $table->decimal('nontaxable_compensation',18, 2)->default(0);
            $table->decimal('sss_ee',          18, 2)->default(0);
            $table->decimal('sss_er',          18, 2)->default(0);
            $table->decimal('phic_ee',         18, 2)->default(0);
            $table->decimal('phic_er',         18, 2)->default(0);
            $table->decimal('hdmf_ee',         18, 2)->default(0);
            $table->decimal('hdmf_er',         18, 2)->default(0);
            $table->decimal('withholding_tax', 18, 2)->default(0);
            $table->decimal('other_deductions',18, 2)->default(0);
            $table->decimal('net_pay',         18, 2)->default(0);
            $table->smallInteger('days_worked')->default(0);
            $table->decimal('hours_worked',    18, 2)->default(0);
            $table->smallInteger('leave_days_used')->default(0);
            $table->decimal('overtime_pay',    18, 2)->default(0);
            $table->decimal('nightdiff_pay',   18, 2)->default(0);
            $table->decimal('holiday_pay',     18, 2)->default(0);
            $table->timestampTz('generated_at')->nullable();
            $table->timestampsTz();

            $table->foreign('payroll_run_id')
                  ->references('id')->on('payroll.payroll_runs')
                  ->onDelete('cascade');

            $table->foreign('employee_id')
                  ->references('id')->on('hr.employees')
                  ->onDelete('restrict');

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index('employee_id');
        });

        Schema::create('payroll.payslip_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payslip_id');
            $table->smallInteger('line_no');
            $table->enum('line_type', ['earning', 'allowance', 'deduction', 'tax', 'statutory', 'loan']);
            $table->string('code', 32);
            $table->string('description');
            $table->decimal('quantity', 18, 4)->nullable();           // hours or days
            $table->decimal('rate',     18, 4)->nullable();
            $table->decimal('amount',   18, 2);                       // negative for deductions
            $table->timestampsTz();

            $table->foreign('payslip_id')
                  ->references('id')->on('payroll.payslips')
                  ->onDelete('cascade');

            $table->unique(['payslip_id', 'line_no']);
        });

        // Statutory remittances — aggregated per agency per period
        Schema::create('payroll.statutory_remittances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_run_id');
            $table->enum('agency', ['SSS', 'PHILHEALTH', 'HDMF', 'BIR']);
            $table->date('period_covered_start');
            $table->date('period_covered_end');
            $table->decimal('ee_total',    18, 2);
            $table->decimal('er_total',    18, 2);
            $table->decimal('grand_total', 18, 2);
            $table->date('due_date')->nullable();
            $table->date('remitted_on')->nullable();
            $table->string('reference_no', 128)->nullable();
            $table->string('file_path', 500)->nullable();             // generated PRN/MCRF/RF-1
            $table->enum('status', ['pending', 'generated', 'filed', 'paid', 'late'])->default('pending');
            $table->timestampsTz();

            $table->foreign('payroll_run_id')
                  ->references('id')->on('payroll.payroll_runs')
                  ->onDelete('restrict');

            $table->index(['agency', 'period_covered_start']);
        });

        // No DELETE on finalized payroll
        DB::unprepared('REVOKE DELETE ON payroll.payroll_runs FROM PUBLIC');
        DB::unprepared('REVOKE DELETE ON payroll.payslips FROM PUBLIC');
        DB::unprepared('REVOKE DELETE ON payroll.payslip_lines FROM PUBLIC');
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll.statutory_remittances');
        Schema::dropIfExists('payroll.payslip_lines');
        Schema::dropIfExists('payroll.payslips');
        Schema::dropIfExists('payroll.payroll_runs');
        Schema::dropIfExists('payroll.payroll_periods');
        Schema::dropIfExists('payroll.compensation_components');
        Schema::dropIfExists('payroll.compensation_packages');
    }
};
