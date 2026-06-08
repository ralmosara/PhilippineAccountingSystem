<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Payroll\Application\Contracts\PayrollRunRepositoryContract;
use App\Modules\Payroll\Application\Contracts\StatutoryRateProviderContract;
use App\Modules\Payroll\Domain\Entities\Payslip;
use App\Modules\Payroll\Domain\Entities\PayslipLine;
use App\Modules\Payroll\Domain\Entities\PayrollRun;
use App\Modules\Payroll\Domain\Events\PayrollRunComputed;
use App\Modules\Payroll\Domain\Services\TrainLawWithholdingCalculator;
use App\Modules\Payroll\Domain\ValueObjects\PayrollFrequency;
use App\Modules\Payroll\Domain\ValueObjects\PayrollRunId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Ramsey\Uuid\Uuid;

/**
 * Generates the annual 13th month pay run per PD 851 (as amended).
 *
 * Computation:
 *   13th month pay = (sum of basic salary earned in the calendar year) / 12
 *
 * "Basic salary" is the BASIC line from approved payslip_lines — excluding
 * overtime, night differential, holiday pay, and allowances.
 *
 * Tax treatment per RA 10963 (TRAIN Law):
 *   - Up to ₱90,000: non-taxable (nontaxable_compensation)
 *   - Excess over ₱90,000: taxable, subject to TRAIN graduated WT
 *
 * No SSS / PhilHealth / Pag-IBIG deductions apply to 13th month pay.
 *
 * Idempotency: if a 13th_month run already exists for the company and year,
 * the action returns it unchanged (callers must void + recompute to regenerate).
 */
final readonly class Generate13thMonthRun
{
    private const NONTAXABLE_CEILING = '90000.00';

    public function __construct(
        private PayrollRunRepositoryContract $runs,
        private StatutoryRateProviderContract $rates,
        private TrainLawWithholdingCalculator $withholding,
        private ConnectionInterface $db,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $companyId, int $year, string $actorId): PayrollRun
    {
        return $this->db->transaction(function () use ($companyId, $year, $actorId) {
            // Guard: one 13th month run per company per year
            $existing = $this->findExisting($companyId, $year);
            if ($existing !== null) {
                return $existing;
            }

            // Payroll period: December 1 – December 24 (PD 851 due date)
            $periodStart = new DateTimeImmutable("{$year}-12-01");
            $periodEnd   = new DateTimeImmutable("{$year}-12-24");

            $periodId = $this->resolveOrCreatePayrollPeriod($companyId, $periodStart, $periodEnd);

            // Pull approved annual basic pay per employee
            $basicByEmployee = $this->aggregateAnnualBasic($companyId, $year);

            // TRAIN brackets active on December 24 of the year (monthly frequency used
            // for WT computation since 13th month is a one-time annual payment)
            $ratePack = $this->rates->ratesEffectiveOn($periodEnd, PayrollFrequency::Monthly);

            $run = new PayrollRun(
                id:              PayrollRunId::generate(),
                payrollPeriodId: $periodId,
                runNo:           $this->runs->nextRunNo($companyId, '13th_month', $year, 12),
                runType:         '13th_month',
            );

            $totalGross = '0.00';
            $payslipCount = 0;

            foreach ($basicByEmployee as $row) {
                $annualBasic     = $row['annual_basic'];        // string numeric
                $thirteenthMonth = bcdiv($annualBasic, '12', 2);

                if (bccomp($thirteenthMonth, '0.00', 2) <= 0) {
                    continue;
                }

                $nontaxable = bccomp($thirteenthMonth, self::NONTAXABLE_CEILING, 2) <= 0
                    ? $thirteenthMonth
                    : self::NONTAXABLE_CEILING;

                $taxable = bcsub($thirteenthMonth, $nontaxable, 2);

                // WT on taxable excess using TRAIN annual rates (no brackets below the
                // first threshold will fire for amounts well below ₱250k annual)
                $withheld = bccomp($taxable, '0.00', 2) > 0
                    ? $this->withholding->compute(
                        Money::php($taxable),
                        $ratePack['bir_brackets'],
                    )
                    : Money::zero();

                $gross   = Money::php($thirteenthMonth);
                $netPay  = $gross->subtract($withheld);

                $payslip = new Payslip(
                    id:                     Uuid::uuid4()->toString(),
                    payrollRunId:           $run->id->value,
                    employeeId:             $row['employee_id'],
                    grossCompensation:      $gross,
                    taxableCompensation:    Money::php($taxable),
                    nontaxableCompensation: Money::php($nontaxable),
                    withholdingTax:         $withheld,
                    netPay:                 $netPay,
                    daysWorked:             0,
                );

                $payslip->addLine(new PayslipLine(1, 'earning', '13TH',   '13th month pay (PD 851)',           $gross));
                if (bccomp($nontaxable, '0.00', 2) > 0) {
                    $payslip->addLine(new PayslipLine(2, 'allowance', 'NONTAX13', 'Non-taxable (≤₱90k, RA 10963)', Money::php($nontaxable)));
                }
                if (bccomp($taxable, '0.00', 2) > 0) {
                    $payslip->addLine(new PayslipLine(3, 'earning', 'TAX13', 'Taxable 13th month excess',          Money::php($taxable)));
                }
                if (! $withheld->isZero()) {
                    $payslip->addLine(new PayslipLine(13, 'tax', 'WT-13TH', 'Withholding tax on 13th month excess', $withheld->negate()));
                }

                $run->addPayslip($payslip);
                $totalGross = bcadd($totalGross, $thirteenthMonth, 2);
                $payslipCount++;
            }

            $run->markComputed(computedBy: $actorId);
            $this->runs->save($run);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'payrollrun.computed',
                aggregate:   'PayrollRun',
                aggregateId: $run->id->value,
                payload: [
                    'run_no'        => $run->runNo,
                    'run_type'      => '13th_month',
                    'year'          => $year,
                    'payslip_count' => $payslipCount,
                    'total_gross'   => $totalGross,
                ],
            );

            $this->events->dispatch(new PayrollRunComputed(
                payrollRunId: $run->id->value,
                companyId:    $companyId,
                runNo:        $run->runNo,
                computedAt:   $run->computedAt,
                computedBy:   $actorId,
                payslipCount: $payslipCount,
                totalGross:   $totalGross,
            ));

            return $run;
        });
    }

    /**
     * @return array{
     *     employee_id: string,
     *     employee_name: string,
     *     annual_basic: string,
     * }[]
     */
    private function aggregateAnnualBasic(string $companyId, int $year): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT
                ps.employee_id,
                e.full_name                             AS employee_name,
                COALESCE(SUM(pl.amount), 0)::text       AS annual_basic
            FROM payroll.payslip_lines pl
            INNER JOIN payroll.payslips         ps ON ps.id = pl.payslip_id
            INNER JOIN payroll.payroll_runs      r  ON r.id  = ps.payroll_run_id
            INNER JOIN payroll.payroll_periods   pp ON pp.id = r.payroll_period_id
            INNER JOIN hr.employees              e  ON e.id  = ps.employee_id
            WHERE pp.company_id     = ?::uuid
              AND r.status          = 'approved'
              AND r.run_type        = 'regular'
              AND EXTRACT(YEAR FROM pp.period_start) = ?
              AND pl.code           = 'BASIC'
              AND pl.line_type      = 'earning'
            GROUP BY ps.employee_id, e.full_name
            HAVING SUM(pl.amount) > 0
            ORDER BY e.full_name
        SQL, [$companyId, $year]);

        return array_map(fn ($r) => [
            'employee_id'   => (string) $r->employee_id,
            'employee_name' => (string) $r->employee_name,
            'annual_basic'  => (string) $r->annual_basic,
        ], $rows);
    }

    private function resolveOrCreatePayrollPeriod(
        string $companyId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): string {
        $existing = $this->db->selectOne(<<<'SQL'
            SELECT id FROM payroll.payroll_periods
             WHERE company_id   = ?::uuid
               AND period_start = ?::date
               AND period_end   = ?::date
        SQL, [$companyId, $start->format('Y-m-d'), $end->format('Y-m-d')]);

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
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $end->format('Y-m-d'),  // pay_date = Dec 24 per PD 851
        ]);

        return $id;
    }

    private function findExisting(string $companyId, int $year): ?PayrollRun
    {
        $row = $this->db->selectOne(<<<'SQL'
            SELECT r.id
              FROM payroll.payroll_runs r
              INNER JOIN payroll.payroll_periods pp ON pp.id = r.payroll_period_id
             WHERE pp.company_id = ?::uuid
               AND r.run_type   = '13th_month'
               AND EXTRACT(YEAR FROM pp.period_start) = ?
             LIMIT 1
        SQL, [$companyId, $year]);

        if ($row === null) {
            return null;
        }

        return $this->runs->findById(new PayrollRunId((string) $row->id));
    }
}
