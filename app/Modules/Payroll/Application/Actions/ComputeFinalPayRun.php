<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Hr\Application\Contracts\EmployeeRepositoryContract;
use App\Modules\Hr\Domain\ValueObjects\EmployeeId;
use App\Modules\Payroll\Application\Contracts\CompensationProviderContract;
use App\Modules\Payroll\Application\Contracts\PayrollRunRepositoryContract;
use App\Modules\Payroll\Application\Contracts\StatutoryRateProviderContract;
use App\Modules\Payroll\Domain\Entities\Payslip;
use App\Modules\Payroll\Domain\Entities\PayslipLine;
use App\Modules\Payroll\Domain\Entities\PayrollRun;
use App\Modules\Payroll\Domain\Events\PayrollRunComputed;
use App\Modules\Payroll\Domain\Services\StatutoryDeductionCalculator;
use App\Modules\Payroll\Domain\ValueObjects\PayrollFrequency;
use App\Modules\Payroll\Domain\ValueObjects\PayrollRunId;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Computes a Final Pay (separation pay) run for a single employee per:
 *   - RA 7641 (Retirement Pay Law)
 *   - Labor Code Art. 302 (retrenchment / redundancy / closure)
 *   - DOLE Labor Advisory on Final Pay
 *
 * Components computed:
 *   1. Last salary   — basic pay for days actually worked in the final period
 *   2. Pro-rated 13th month pay — basic / 12 × months_worked_this_year
 *   3. Unused SIL   — 5 days/year × years_of_service (pro-rated to completed months)
 *   4. Separation pay (only when NOT resigned):
 *        - redundancy / retrenchment / closure / illness → 1 month per year (min ½ month)
 *        - terminated (without just cause) → ½ month per year (min 1 month)
 *   5. Mandatory deductions — SSS / PhilHealth / Pag-IBIG for the final period
 *
 * All arithmetic uses BCMath at 4 decimal places; money values are
 * stored as decimal(18,2). No float is used anywhere in this action.
 */
final readonly class ComputeFinalPayRun
{
    /** Service Incentive Leave mandated by Labor Code Art. 95 */
    private const SIL_DAYS_PER_YEAR = '5';

    /** Standard working days per month used to convert daily rate */
    private const WORKING_DAYS_PER_MONTH = '26';

    public function __construct(
        private CompensationProviderContract $compensation,
        private StatutoryRateProviderContract $rates,
        private StatutoryDeductionCalculator $statutory,
        private PayrollRunRepositoryContract $runs,
        private EmployeeRepositoryContract $employees,
        private AuditWriterContract $audit,
        private Dispatcher $events,
        private ConnectionInterface $db,
    ) {
    }

    /**
     * @param  string  $separationReason  resigned|terminated|redundancy|retrenchment|closure|illness|other
     * @throws RuntimeException when the employee or compensation package cannot be found
     */
    public function execute(
        string $employeeId,
        string $companyId,
        string $separationDate,
        string $separationReason,
        string $actorId,
    ): PayrollRun {
        return $this->db->transaction(function () use (
            $employeeId, $companyId, $separationDate, $separationReason, $actorId
        ) {
            $sepDate = new DateTimeImmutable($separationDate);

            // --- 1. Resolve employee -------------------------------------------------------
            $employee = $this->employees->findById(
                new EmployeeId($employeeId)
            );
            if ($employee === null) {
                throw new RuntimeException("Employee {$employeeId} not found.");
            }

            // --- 2. Compensation package as of separation date ----------------------------
            $comp = $this->compensation->findActive($employeeId, $sepDate);
            if ($comp === null) {
                throw new RuntimeException(
                    "No active compensation package for employee {$employeeId} on {$separationDate}."
                );
            }

            $monthlyBasic = $comp['basic_monthly'];    // string numeric

            // --- 3. Statutory rates --------------------------------------------------------
            $ratePack = $this->rates->ratesEffectiveOn($sepDate, PayrollFrequency::Monthly);

            // --- 4. Derive service metrics ------------------------------------------------
            $hiredOn       = $employee->hiredOn;
            $hiredCarbon   = CarbonImmutable::instance($hiredOn);
            $sepCarbon     = CarbonImmutable::instance($sepDate);

            // Full years of service (floor)
            $yearsOfService = (string) $hiredCarbon->diffInYears($sepCarbon);

            // Months worked in the current calendar year (for 13th month)
            $yearStart           = CarbonImmutable::create($sepCarbon->year, 1, 1);
            $monthsWorkedThisYear = (string) max(
                1,
                (int) $yearStart->diffInMonths($sepCarbon) + 1  // +1 to include partial month
            );

            // Days worked in the final pay period (from 1st of separation month to separation date)
            $periodStart  = CarbonImmutable::create($sepCarbon->year, $sepCarbon->month, 1);
            $daysWorked   = (string) $this->workingDaysBetween($periodStart, $sepCarbon);

            // --- 5. Compute component amounts (BCMath only) --------------------------------
            $dailyRate = bcdiv($monthlyBasic, self::WORKING_DAYS_PER_MONTH, 4);

            // 5a. Last salary
            $lastSalary = bcmul($dailyRate, $daysWorked, 4);

            // 5b. Pro-rated 13th month pay  = (basic / 12) × months_worked_this_year
            $proRated13th = bcmul(
                bcdiv($monthlyBasic, '12', 4),
                $monthsWorkedThisYear,
                4,
            );

            // 5c. Unused SIL  = (5 days / 12) × months_of_service × daily_rate
            //     We credit full years only (per DOLE convention unless CBA says otherwise)
            $silDays    = bcmul(self::SIL_DAYS_PER_YEAR, $yearsOfService, 4);
            $unusedSil  = bcmul($silDays, $dailyRate, 4);

            // 5d. Separation pay (zero if resigned or other without entitlement)
            $separationPay = $this->computeSeparationPay(
                reason:         $separationReason,
                monthlyBasic:   $monthlyBasic,
                yearsOfService: $yearsOfService,
            );

            // 5e. Statutory deductions (on monthly basic; final period contribution)
            $monthlyBasicMoney = Money::php($monthlyBasic);
            $sss        = $this->statutory->computeSss($monthlyBasicMoney, $ratePack['sss_brackets']);
            $philhealth = $this->statutory->computePhilhealth($monthlyBasicMoney, $ratePack['philhealth']);
            $pagibig    = $this->statutory->computePagibig($monthlyBasicMoney, $ratePack['pagibig']);

            // --- 6. Build gross and net ---------------------------------------------------
            $totalEarnings = bcadd(
                bcadd(
                    bcadd($lastSalary, $proRated13th, 4),
                    $unusedSil,
                    4,
                ),
                $separationPay,
                4,
            );

            $totalDeductionsStr = bcadd(
                bcadd($sss['ee']->amount, $philhealth['ee']->amount, 4),
                $pagibig['ee']->amount,
                4,
            );

            $netPayStr = bcsub($totalEarnings, $totalDeductionsStr, 4);

            // --- 7. Build PayrollRun + Payslip ------------------------------------------
            $periodId = $this->resolveOrCreatePayrollPeriod(
                companyId:   $companyId,
                periodStart: new DateTimeImmutable($periodStart->format('Y-m-d')),
                periodEnd:   $sepDate,
            );

            $year  = (int) $sepDate->format('Y');
            $month = (int) $sepDate->format('m');

            $run = new PayrollRun(
                id:              PayrollRunId::generate(),
                payrollPeriodId: $periodId,
                runNo:           $this->runs->nextRunNo($companyId, 'final_pay', $year, $month),
                runType:         'final_pay',
            );

            $payslip = new Payslip(
                id:                     Uuid::uuid4()->toString(),
                payrollRunId:           $run->id->value,
                employeeId:             $employeeId,
                grossCompensation:      Money::php($totalEarnings),
                taxableCompensation:    Money::php($totalEarnings),   // simplified: full gross is taxable
                nontaxableCompensation: Money::zero(),
                sssEe:                  $sss['ee'],
                sssEr:                  $sss['er'],
                phicEe:                 $philhealth['ee'],
                phicEr:                 $philhealth['er'],
                hdmfEe:                 $pagibig['ee'],
                hdmfEr:                 $pagibig['er'],
                withholdingTax:         Money::zero(),        // BIR: final pay WT handled separately
                otherDeductions:        Money::zero(),
                netPay:                 Money::php($netPayStr),
                daysWorked:             (int) $daysWorked,
                hoursWorked:            bcmul($daysWorked, '8', 0),
            );

            // Lines — earnings
            $lineNo = 1;
            $payslip->addLine(new PayslipLine(
                $lineNo++, 'earning', 'LAST-SAL',
                'Last salary (' . $daysWorked . ' days worked)',
                Money::php($lastSalary),
                $daysWorked,
                $dailyRate,
            ));

            $payslip->addLine(new PayslipLine(
                $lineNo++, 'earning', 'PRO13TH',
                'Pro-rated 13th month pay (' . $monthsWorkedThisYear . ' months, ' . $year . ')',
                Money::php($proRated13th),
            ));

            if (bccomp($unusedSil, '0', 4) > 0) {
                $payslip->addLine(new PayslipLine(
                    $lineNo++, 'earning', 'SIL',
                    'Unused service incentive leave (' . $silDays . ' days)',
                    Money::php($unusedSil),
                    $silDays,
                    $dailyRate,
                ));
            }

            if (bccomp($separationPay, '0', 4) > 0) {
                $payslip->addLine(new PayslipLine(
                    $lineNo++, 'earning', 'SEP-PAY',
                    'Separation pay (' . $yearsOfService . ' yrs × ' . $this->separationLabel($separationReason) . ')',
                    Money::php($separationPay),
                ));
            }

            // Lines — deductions
            $payslip->addLine(new PayslipLine(
                10, 'deduction', 'SSS',
                'SSS contribution (final period)',
                $sss['ee']->negate(),
            ));
            $payslip->addLine(new PayslipLine(
                11, 'deduction', 'PHIC',
                'PhilHealth contribution (final period)',
                $philhealth['ee']->negate(),
            ));
            $payslip->addLine(new PayslipLine(
                12, 'deduction', 'HDMF',
                'Pag-IBIG contribution (final period)',
                $pagibig['ee']->negate(),
            ));

            $run->addPayslip($payslip);
            $run->markComputed(computedBy: $actorId);

            // --- 8. Persist ---------------------------------------------------------------
            $this->runs->save($run);

            // --- 9. Audit + event --------------------------------------------------------
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'payrollrun.computed',
                aggregate:   'PayrollRun',
                aggregateId: $run->id->value,
                payload: [
                    'run_no'            => $run->runNo,
                    'run_type'          => 'final_pay',
                    'employee_id'       => $employeeId,
                    'separation_date'   => $separationDate,
                    'separation_reason' => $separationReason,
                    'years_of_service'  => $yearsOfService,
                    'total_gross'       => bcadd($totalEarnings, '0', 2),
                    'total_net'         => bcadd($netPayStr, '0', 2),
                ],
            );

            $this->events->dispatch(new PayrollRunComputed(
                payrollRunId: $run->id->value,
                companyId:    $companyId,
                runNo:        $run->runNo,
                computedAt:   $run->computedAt,
                computedBy:   $actorId,
                payslipCount: 1,
                totalGross:   bcadd($totalEarnings, '0', 2),
            ));

            return $run;
        });
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /**
     * Separation pay per Labor Code (RA 7641 / Art. 298-299):
     *
     *   resigned / other          → ₱0   (no statutory entitlement)
     *   terminated (unjust)       → ½ month per year, minimum 1 month
     *   redundancy / retrenchment
     *     / closure / illness     → 1 month per year, minimum ½ month
     *
     * "1 month" here = monthly basic salary (not daily-rate × 30).
     * Minimum is enforced per DOLE interpretation.
     */
    private function computeSeparationPay(
        string $reason,
        string $monthlyBasic,
        string $yearsOfService,
    ): string {
        return match ($reason) {
            'resigned', 'other' => '0',

            'terminated' => $this->separationPayAtRate(
                monthlyBasic:   $monthlyBasic,
                yearsOfService: $yearsOfService,
                rate:           '0.5',
                minimum:        $monthlyBasic,          // min 1 month for unjust dismissal
            ),

            'redundancy', 'retrenchment', 'closure', 'illness' => $this->separationPayAtRate(
                monthlyBasic:   $monthlyBasic,
                yearsOfService: $yearsOfService,
                rate:           '1',
                minimum:        bcdiv($monthlyBasic, '2', 4),  // min ½ month
            ),

            default => '0',
        };
    }

    /**
     * @param  string  $rate    BCMath multiplier (e.g. '1' or '0.5')
     */
    private function separationPayAtRate(
        string $monthlyBasic,
        string $yearsOfService,
        string $rate,
        string $minimum,
    ): string {
        // At least 1 year for any service (fractional years round up per DOLE)
        $years      = bccomp($yearsOfService, '1', 4) < 0 ? '1' : $yearsOfService;
        $computed   = bcmul(bcmul($monthlyBasic, $rate, 4), $years, 4);

        // Enforce minimum
        return bccomp($computed, $minimum, 4) >= 0 ? $computed : $minimum;
    }

    private function separationLabel(string $reason): string
    {
        return match ($reason) {
            'terminated'   => '½ mo/yr (unjust)',
            'redundancy'   => '1 mo/yr (redundancy)',
            'retrenchment' => '1 mo/yr (retrenchment)',
            'closure'      => '1 mo/yr (closure)',
            'illness'      => '1 mo/yr (illness)',
            default        => 'N/A',
        };
    }

    private function workingDaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $days    = 0;
        $current = $from;
        while ($current->lessThanOrEqualTo($to)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current = $current->addDay();
        }
        return $days;
    }

    private function resolveOrCreatePayrollPeriod(
        string $companyId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
    ): string {
        $existing = $this->db->selectOne(<<<'SQL'
            SELECT id FROM payroll.payroll_periods
             WHERE company_id   = ?::uuid
               AND period_start = ?::date
               AND period_end   = ?::date
        SQL, [$companyId, $periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d')]);

        if ($existing !== null) {
            return (string) $existing->id;
        }

        $id = Uuid::uuid4()->toString();
        $this->db->insert(<<<'SQL'
            INSERT INTO payroll.payroll_periods
                (id, company_id, frequency, period_start, period_end, pay_date, created_at, updated_at)
            VALUES (?::uuid, ?::uuid, 'monthly', ?::date, ?::date, ?::date, NOW(), NOW())
        SQL, [
            $id,
            $companyId,
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
        ]);

        return $id;
    }
}
