<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Entities;

final readonly class FinancialStatementLine
{
    public function __construct(
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public string $amount,                  // absolute value (positive)
        public ?string $pfrsClassification = null,
    ) {
    }
}
