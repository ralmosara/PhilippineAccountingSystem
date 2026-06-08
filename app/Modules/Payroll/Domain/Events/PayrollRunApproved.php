<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Events;

use DateTimeImmutable;

final readonly class PayrollRunApproved
{
    public function __construct(
        public string $payrollRunId,
        public string $companyId,
        public string $runNo,
        public string $journalEntryId,
        public DateTimeImmutable $approvedAt,
        public string $approvedBy,
        public string $totalNetPay,
        public string $totalWithholdingTax,
    ) {
    }
}
