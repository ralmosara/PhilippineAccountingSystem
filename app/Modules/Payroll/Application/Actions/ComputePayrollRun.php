<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Hr\Application\Contracts\EmployeeRepositoryContract;
use App\Modules\Payroll\Application\Contracts\CompensationProviderContract;
use App\Modules\Payroll\Application\Contracts\PayrollRunRepositoryContract;
use App\Modules\Payroll\Application\Contracts\StatutoryRateProviderContract;
use App\Modules\Payroll\Domain\Entities\PayrollRun;
use App\Modules\Payroll\Domain\Events\PayrollRunComputed;
use App\Modules\Payroll\Domain\Services\PayrollComputationService;
use App\Modules\Payroll\Domain\ValueObjects\PayrollFrequency;
use App\Modules\Payroll\Domain\ValueObjects\PayrollRunId;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Computes a payroll run for a given period.
 *
 * Flow:
 *   1. Resolve effective statutory rate tables for the period (SSS/PHIC/HDMF/BIR)
 *   2. List active employees for the company (via HR's EmployeeRepository)
 *   3. For each employee:
 *      - Pull active compensation package
 *      - Compute statutory deductions + WT (PayrollComputationService)
 *      - Build Payslip + lines
 *   4. Persist PayrollRun + all Payslips (within one tx)
 *   5. Audit + dispatch PayrollRunComputed
 *
 * No JV is posted at this stage — approval is required first (separation
 * of computation from financial commitment).
 */
final readonly class ComputePayrollRun
{
    public function __construct(
        private PayrollRunRepositoryContract $runs,
        private EmployeeRepositoryContract $employees,
        private CompensationProviderContract $compensation,
        private StatutoryRateProviderContract $rates,
        private PayrollComputationService $computation,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(
        string $companyId,
        string $payrollPeriodId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        PayrollFrequency $frequency,
        string $runType,                         // 'regular' | '13th_month' | 'final_pay' | 'adjustment'
        string $actorId,
    ): PayrollRun {
        return DB::transaction(function () use (
            $companyId, $payrollPeriodId, $periodStart, $periodEnd, $frequency, $runType, $actorId
        ) {
            // 1. Statutory rates for the period
            $ratePack = $this->rates->ratesEffectiveOn($periodEnd, $frequency);

            // 2. Build the run
            $year  = (int) $periodEnd->format('Y');
            $month = (int) $periodEnd->format('m');

            $run = new PayrollRun(
                id:              PayrollRunId::generate(),
                payrollPeriodId: $payrollPeriodId,
                runNo:           $this->runs->nextRunNo($companyId, $runType, $year, $month),
                runType:         $runType,
            );

            // 3. Loop active employees and compute payslips
            $totalGross = '0';
            $payslipCount = 0;

            foreach ($this->employees->listActiveForCompany($companyId) as $employee) {
                $comp = $this->compensation->findActive($employee->id->value, $periodEnd);
                if ($comp === null) {
                    continue;       // no active compensation package — skip
                }

                $payslip = $this->computation->compute(
                    input: [
                        'payroll_run_id'         => $run->id->value,
                        'employee_id'            => $employee->id->value,
                        'monthly_basic'          => $comp['basic_monthly'],
                        'taxable_allowances'     => $comp['taxable_allowances'],
                        'nontaxable_allowances'  => $comp['nontaxable_allowances'],
                        'is_minimum_wage_earner' => $comp['is_minimum_wage_earner'],
                        'days_worked'            => $this->workingDaysIn($periodStart, $periodEnd),
                        'hours_worked'           => (string) ($this->workingDaysIn($periodStart, $periodEnd) * 8),
                        'overtime_pay'           => '0',
                        'nightdiff_pay'          => '0',
                        'holiday_pay'            => '0',
                        'other_deductions'       => '0',
                    ],
                    rates: $ratePack,
                );

                $run->addPayslip($payslip);
                $totalGross = bcadd($totalGross, $payslip->grossCompensation->toPhp(), 2);
                $payslipCount++;
            }

            $run->markComputed(computedBy: $actorId);

            // 4. Persist
            $this->runs->save($run);

            // 5. Audit + event
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'payrollrun.computed',
                aggregate:   'PayrollRun',
                aggregateId: $run->id->value,
                payload: [
                    'run_no'         => $run->runNo,
                    'run_type'       => $run->runType,
                    'period'         => $periodStart->format('Y-m-d').'..'.$periodEnd->format('Y-m-d'),
                    'payslip_count'  => $payslipCount,
                    'total_gross'    => $totalGross,
                ],
            );

            $this->events->dispatch(new PayrollRunComputed(
                payrollRunId:  $run->id->value,
                companyId:     $companyId,
                runNo:         $run->runNo,
                computedAt:    $run->computedAt,
                computedBy:    $actorId,
                payslipCount:  $payslipCount,
                totalGross:    $totalGross,
            ));

            return $run;
        });
    }

    private function workingDaysIn(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        // Mon-Fri count between dates (inclusive). Holiday calendar
        // refinement comes when the HR Attendance module lands.
        $start   = CarbonImmutable::instance($from);
        $end     = CarbonImmutable::instance($to);
        $days    = 0;
        $current = $start;

        while ($current->lessThanOrEqualTo($end)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current = $current->addDay();
        }
        return $days;
    }
}
