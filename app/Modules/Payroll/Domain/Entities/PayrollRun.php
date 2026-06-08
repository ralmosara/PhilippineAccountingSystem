<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Entities;

use App\Modules\Payroll\Domain\ValueObjects\PayrollRunId;
use DateTimeImmutable;
use DomainException;

final class PayrollRun
{
    /** @var array<int, Payslip> */
    public array $payslips = [];

    public ?DateTimeImmutable $computedAt = null;

    public ?DateTimeImmutable $approvedAt = null;

    public ?string $approvedBy = null;

    public ?DateTimeImmutable $paidAt = null;

    public ?string $journalEntryId = null;

    public string $status = 'draft';

    public function __construct(
        public readonly PayrollRunId $id,
        public readonly string $payrollPeriodId,
        public readonly string $runNo,
        public readonly string $runType = 'regular',          // regular | 13th_month | final_pay | adjustment
    ) {
    }

    public function addPayslip(Payslip $slip): void
    {
        $this->payslips[] = $slip;
    }

    public function markComputed(string $computedBy): void
    {
        $this->computedAt = new DateTimeImmutable();
        $this->status     = 'computed';
    }

    public function approve(string $approver): void
    {
        if ($this->status !== 'computed') {
            throw new DomainException("Cannot approve a payroll run in status: {$this->status}");
        }
        $this->approvedAt = new DateTimeImmutable();
        $this->approvedBy = $approver;
        $this->status     = 'approved';
    }
}
