<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence;

use App\Modules\Payroll\Application\Contracts\StatutoryRemittanceAggregatorContract;
use App\Modules\Payroll\Domain\Entities\StatutoryRemittanceLine;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class EloquentStatutoryRemittanceAggregator implements StatutoryRemittanceAggregatorContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function aggregateForPeriod(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        // Sum employee + employer contributions across every APPROVED payroll
        // run whose period overlaps [from, to]. Group by employee so we emit
        // one row per worker even if multiple semimonthly runs cover the period.
        //
        // Joins:
        //   payroll.payslip_lines  ─►  employee deductions per run
        //   payroll.payroll_runs   ─►  filter by status='approved' + period
        //   hr.employees           ─►  pull SSS/PHIC/HDMF numbers + TIN + name
        //
        // We accept a soft cross-schema join because the relationship is
        // structural (every payslip line belongs to an employee); the
        // alternative of a contract-only call would issue N+1 queries.
        $rows = $this->db->select(<<<'SQL'
            SELECT
                pl.employee_id,
                e.full_name                          AS employee_name,
                e.sss_no                             AS sss_number,
                e.philhealth_no                      AS philhealth_number,
                e.pagibig_no                         AS pagibig_number,
                e.tin                                AS tin,
                COALESCE(SUM(pl.gross),       0)     AS compensation,
                COALESCE(SUM(pl.sss_ee),      0)     AS sss_ee,
                COALESCE(SUM(pl.sss_er),      0)     AS sss_er,
                COALESCE(SUM(pl.sss_ec),      0)     AS sss_ec,
                COALESCE(SUM(pl.phic_ee),     0)     AS phic_ee,
                COALESCE(SUM(pl.phic_er),     0)     AS phic_er,
                COALESCE(SUM(pl.hdmf_ee),     0)     AS hdmf_ee,
                COALESCE(SUM(pl.hdmf_er),     0)     AS hdmf_er
            FROM payroll.payslip_lines pl
            INNER JOIN payroll.payroll_runs r ON r.id = pl.payroll_run_id
            INNER JOIN hr.employees e        ON e.id = pl.employee_id
            WHERE r.company_id  = ?::uuid
              AND r.status      = 'approved'
              AND r.period_start <= ?::date
              AND r.period_end   >= ?::date
            GROUP BY pl.employee_id, e.full_name, e.sss_no, e.philhealth_no, e.pagibig_no, e.tin
            ORDER BY e.full_name
        SQL, [$companyId, $to->format('Y-m-d'), $from->format('Y-m-d')]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = new StatutoryRemittanceLine(
                employeeId:       (string) $r->employee_id,
                employeeName:     (string) $r->employee_name,
                sssNumber:        $r->sss_number        !== null ? (string) $r->sss_number        : null,
                philhealthNumber: $r->philhealth_number !== null ? (string) $r->philhealth_number : null,
                pagibigNumber:    $r->pagibig_number    !== null ? (string) $r->pagibig_number    : null,
                tin:              $r->tin               !== null ? (string) $r->tin               : null,
                compensation:     (string) $r->compensation,
                sssEe:            (string) $r->sss_ee,
                sssEr:            (string) $r->sss_er,
                sssEc:            (string) $r->sss_ec,
                phicEe:           (string) $r->phic_ee,
                phicEr:           (string) $r->phic_er,
                hdmfEe:           (string) $r->hdmf_ee,
                hdmfEr:           (string) $r->hdmf_er,
            );
        }
        return $out;
    }
}
