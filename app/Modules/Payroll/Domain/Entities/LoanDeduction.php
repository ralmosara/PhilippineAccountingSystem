<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Entities;

use App\Modules\Payroll\Domain\ValueObjects\LoanType;
use DateTimeImmutable;

/**
 * Represents a single SSS salary loan or HDMF (Pag-IBIG) loan registered
 * against an employee. Monthly amortization is deducted from each payslip
 * when this loan is active on the pay-period end date.
 *
 * All monetary amounts are stored as BCMath strings (numeric(18,2)).
 */
final class LoanDeduction
{
    public function __construct(
        public readonly string            $id,
        public readonly string            $companyId,
        public readonly string            $employeeId,
        public readonly LoanType          $loanType,
        public readonly string            $loanReference,
        /** BCMath string — original principal */
        public readonly string            $originalAmount,
        /** BCMath string — remaining balance (decremented on each payroll run) */
        public readonly string            $outstandingBalance,
        /** BCMath string — fixed monthly instalment */
        public readonly string            $monthlyAmortization,
        public readonly DateTimeImmutable $startedOn,
        public readonly ?DateTimeImmutable $endsOn,
        public readonly bool              $isActive,
        public readonly ?string           $notes,
    ) {
    }

    /**
     * Returns true when this loan should produce a deduction line on a payslip
     * computed for the given date.
     */
    public function isActiveOnDate(DateTimeImmutable $date): bool
    {
        if (! $this->isActive) {
            return false;
        }

        // started_on <= $date
        if ($this->startedOn > $date) {
            return false;
        }

        // ends_on IS NULL OR ends_on >= $date
        if ($this->endsOn !== null && $this->endsOn < $date) {
            return false;
        }

        return true;
    }
}
