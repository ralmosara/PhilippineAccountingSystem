<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Entities;

use App\Modules\Payroll\Domain\ValueObjects\StatutoryAgency;

/**
 * One employee's row across all three statutory agencies for the period.
 * The formatter picks the relevant subset per agency at format time —
 * keeping all three sets on one row avoids running the SQL aggregation
 * three times when an operator files all three remittances back-to-back.
 *
 * All amounts are decimal strings (BCMath); never floats.
 */
final readonly class StatutoryRemittanceLine
{
    public function __construct(
        public string $employeeId,
        public string $employeeName,
        public ?string $sssNumber,
        public ?string $philhealthNumber,
        public ?string $pagibigNumber,
        public ?string $tin,
        public string $compensation,
        public string $sssEe,
        public string $sssEr,
        public string $sssEc,            // SSS employer EC (Employees' Compensation Program)
        public string $phicEe,
        public string $phicEr,
        public string $hdmfEe,
        public string $hdmfEr,
    ) {
    }

    /** Per-agency total for this employee (EE + ER + EC where applicable). */
    public function totalRemittanceFor(StatutoryAgency $agency): string
    {
        return match ($agency) {
            StatutoryAgency::Sss        => bcadd(bcadd($this->sssEe, $this->sssEr, 2), $this->sssEc, 2),
            StatutoryAgency::PhilHealth => bcadd($this->phicEe, $this->phicEr, 2),
            StatutoryAgency::PagIbig    => bcadd($this->hdmfEe, $this->hdmfEr, 2),
        };
    }

    /** Per-agency employee share. */
    public function employeeShareFor(StatutoryAgency $agency): string
    {
        return match ($agency) {
            StatutoryAgency::Sss        => $this->sssEe,
            StatutoryAgency::PhilHealth => $this->phicEe,
            StatutoryAgency::PagIbig    => $this->hdmfEe,
        };
    }

    /** Per-agency employer share. */
    public function employerShareFor(StatutoryAgency $agency): string
    {
        return match ($agency) {
            StatutoryAgency::Sss        => $this->sssEr,
            StatutoryAgency::PhilHealth => $this->phicEr,
            StatutoryAgency::PagIbig    => $this->hdmfEr,
        };
    }

    /** Per-agency identifier (SSS number / PHIC number / HDMF number). */
    public function memberNumberFor(StatutoryAgency $agency): ?string
    {
        return match ($agency) {
            StatutoryAgency::Sss        => $this->sssNumber,
            StatutoryAgency::PhilHealth => $this->philhealthNumber,
            StatutoryAgency::PagIbig    => $this->pagibigNumber,
        };
    }
}
