<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;

final class Payslip
{
    /** @var array<int, PayslipLine> */
    public array $lines = [];

    public function __construct(
        public readonly string $id,                 // uuid
        public readonly string $payrollRunId,
        public readonly string $employeeId,
        public Money $grossCompensation = new Money('0.0000', 'PHP'),
        public Money $taxableCompensation = new Money('0.0000', 'PHP'),
        public Money $nontaxableCompensation = new Money('0.0000', 'PHP'),
        public Money $sssEe = new Money('0.0000', 'PHP'),
        public Money $sssEr = new Money('0.0000', 'PHP'),
        public Money $phicEe = new Money('0.0000', 'PHP'),
        public Money $phicEr = new Money('0.0000', 'PHP'),
        public Money $hdmfEe = new Money('0.0000', 'PHP'),
        public Money $hdmfEr = new Money('0.0000', 'PHP'),
        public Money $withholdingTax = new Money('0.0000', 'PHP'),
        public Money $otherDeductions = new Money('0.0000', 'PHP'),
        public Money $netPay = new Money('0.0000', 'PHP'),
        public int $daysWorked = 0,
        public string $hoursWorked = '0',
        public Money $overtimePay = new Money('0.0000', 'PHP'),
        public Money $nightdiffPay = new Money('0.0000', 'PHP'),
        public Money $holidayPay = new Money('0.0000', 'PHP'),
    ) {
    }

    public function addLine(PayslipLine $line): void
    {
        $this->lines[] = $line;
    }

    /** Total deductions (employee side) = SSS + PHIC + HDMF + WT + other */
    public function totalDeductions(): Money
    {
        return $this->sssEe
            ->add($this->phicEe)
            ->add($this->hdmfEe)
            ->add($this->withholdingTax)
            ->add($this->otherDeductions);
    }

    /** Recomputes net_pay from current values. */
    public function recalcNetPay(): void
    {
        $this->netPay = $this->grossCompensation->subtract($this->totalDeductions());
    }
}
