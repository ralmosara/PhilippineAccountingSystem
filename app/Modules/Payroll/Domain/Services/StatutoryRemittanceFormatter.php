<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Services;

use App\Modules\Payroll\Domain\Entities\StatutoryRemittanceLine;
use App\Modules\Payroll\Domain\ValueObjects\StatutoryAgency;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Produces the agency-specific remittance file string. Pure: takes lines +
 * employer context, returns the file body. Encoding-safe (UTF-8 + CRLF
 * for SSS, ASCII + CRLF for PhilHealth + Pag-IBIG per their respective
 * portal requirements).
 *
 * Agency format references (as of 2026):
 *
 *   - SSS R-3:        SSS Circular 2020-033 (RMS portal CSV spec)
 *   - PhilHealth RF-1: PhilHealth Circular 2020-005 (EPRS CSV spec)
 *   - Pag-IBIG MCRF:  HDMF Circular 460 (Virtual Pag-IBIG CSV upload)
 *
 * Each agency's portal validates the format on upload. A field-order
 * mismatch causes the entire file to reject — that's why these tests are
 * pinned byte-exact (tests/Unit/Modules/Payroll/StatutoryRemittanceFormatterTest.php).
 */
final readonly class StatutoryRemittanceFormatter
{
    /**
     * @param array{
     *     er_id: string,
     *     er_name: string,
     *     er_address: string,
     *     er_tin: string,
     *     er_branch_code: string,
     * } $employerHeader
     * @param list<StatutoryRemittanceLine> $lines
     */
    public function format(
        StatutoryAgency $agency,
        array $employerHeader,
        array $lines,
        DateTimeImmutable $periodFrom,
        DateTimeImmutable $periodTo,
    ): string {
        $this->assertEmployerHeader($employerHeader);

        return match ($agency) {
            StatutoryAgency::Sss        => $this->formatSssR3($employerHeader, $lines, $periodFrom, $periodTo),
            StatutoryAgency::PhilHealth => $this->formatPhilHealthRf1($employerHeader, $lines, $periodFrom, $periodTo),
            StatutoryAgency::PagIbig    => $this->formatPagIbigMcrf($employerHeader, $lines, $periodFrom, $periodTo),
        };
    }

    /**
     * SSS R-3 CSV format (RMS portal):
     *   header:   H,<ER_ID>,<ER_NAME>,<PERIOD_FROM>,<PERIOD_TO>,<LINE_COUNT>
     *   detail:   D,<SSS_NO>,<EMPLOYEE_NAME>,<COMPENSATION>,<EE_SHARE>,<ER_SHARE>,<EC>,<TOTAL>
     *   CRLF separated, dash-stripped SSS numbers (10 digits).
     */
    private function formatSssR3(array $er, array $lines, DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        $out = sprintf(
            "H,%s,%s,%s,%s,%d\r\n",
            $er['er_id'],
            $this->truncate($er['er_name'], 100),
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            count($lines),
        );

        foreach ($lines as $l) {
            $sss = $l->sssNumber !== null ? $this->stripDashes($l->sssNumber) : '';
            $out .= sprintf(
                "D,%s,%s,%s,%s,%s,%s,%s\r\n",
                $sss,
                $this->truncate($l->employeeName, 100),
                $this->money2($l->compensation),
                $this->money2($l->sssEe),
                $this->money2($l->sssEr),
                $this->money2($l->sssEc),
                $this->money2($l->totalRemittanceFor(StatutoryAgency::Sss)),
            );
        }

        return $out;
    }

    /**
     * PhilHealth RF-1 CSV format (EPRS portal):
     *   header:   H|<PEN>|<ER_NAME>|<PERIOD_YYYYMM>|<LINE_COUNT>
     *   detail:   D|<PHIC_NO>|<LAST,FIRST,MIDDLE>|<COMPENSATION>|<EE_SHARE>|<ER_SHARE>|<TOTAL>
     *   Pipe-separated, CRLF, dash-stripped PHIC numbers (12 digits).
     */
    private function formatPhilHealthRf1(array $er, array $lines, DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        $period = $from->format('Ym');
        $out = sprintf(
            "H|%s|%s|%s|%d\r\n",
            $this->stripDashes($er['er_id']),
            $this->truncate($er['er_name'], 100),
            $period,
            count($lines),
        );

        foreach ($lines as $l) {
            $phic = $l->philhealthNumber !== null ? $this->stripDashes($l->philhealthNumber) : '';
            $out .= sprintf(
                "D|%s|%s|%s|%s|%s|%s\r\n",
                $phic,
                $this->truncate($l->employeeName, 100),
                $this->money2($l->compensation),
                $this->money2($l->phicEe),
                $this->money2($l->phicEr),
                $this->money2($l->totalRemittanceFor(StatutoryAgency::PhilHealth)),
            );
        }

        return $out;
    }

    /**
     * Pag-IBIG MCRF CSV format (Virtual Pag-IBIG portal):
     *   header:   H,<ER_NO>,<ER_NAME>,<APPLICABLE_PERIOD_MM/YYYY>,<LINE_COUNT>
     *   detail:   D,<HDMF_NO>,<EMPLOYEE_NAME>,<COMPENSATION>,<EE_SHARE>,<ER_SHARE>,<TOTAL>
     *   Comma-separated, CRLF, dash-stripped HDMF numbers (12 digits).
     */
    private function formatPagIbigMcrf(array $er, array $lines, DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        $applicablePeriod = $from->format('m/Y');
        $out = sprintf(
            "H,%s,%s,%s,%d\r\n",
            $this->stripDashes($er['er_id']),
            $this->truncate($er['er_name'], 100),
            $applicablePeriod,
            count($lines),
        );

        foreach ($lines as $l) {
            $hdmf = $l->pagibigNumber !== null ? $this->stripDashes($l->pagibigNumber) : '';
            $total = bcadd($l->hdmfEe, $l->hdmfEr, 2);
            $out .= sprintf(
                "D,%s,%s,%s,%s,%s,%s\r\n",
                $hdmf,
                $this->truncate($l->employeeName, 100),
                $this->money2($l->compensation),
                $this->money2($l->hdmfEe),
                $this->money2($l->hdmfEr),
                $this->money2($total),
            );
        }

        return $out;
    }

    /* ── helpers ────────────────────────────────────────────────────── */

    private function assertEmployerHeader(array $h): void
    {
        foreach (['er_id', 'er_name', 'er_address', 'er_tin'] as $required) {
            if (! isset($h[$required]) || $h[$required] === '') {
                throw new InvalidArgumentException("Employer header missing '{$required}'.");
            }
        }
    }

    private function stripDashes(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    private function truncate(string $s, int $max): string
    {
        return mb_substr($s, 0, $max);
    }

    /** Force 2dp without rounding (BCMath truncate behaviour matches DAT format). */
    private function money2(string $s): string
    {
        $scaled = bcadd($s, '0', 2);
        // Empty / zero edge case
        return $scaled === '' ? '0.00' : $scaled;
    }
}
