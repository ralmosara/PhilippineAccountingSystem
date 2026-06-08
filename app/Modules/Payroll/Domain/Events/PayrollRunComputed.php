<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Events;

use DateTimeImmutable;

final readonly class PayrollRunComputed
{
    public function __construct(
        public string $payrollRunId,
        public string $companyId,
        public string $runNo,
        public DateTimeImmutable $computedAt,
        public string $computedBy,
        public int $payslipCount,
        public string $totalGross,
    ) {
    }
}
