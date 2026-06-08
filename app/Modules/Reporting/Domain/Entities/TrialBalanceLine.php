<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Entities;

final readonly class TrialBalanceLine
{
    public function __construct(
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public string $accountType,       // asset|liability|equity|revenue|expense|contra_*
        public string $normalBalance,     // 'debit' | 'credit'
        public string $debit,              // sum of debit lines in period
        public string $credit,             // sum of credit lines in period
        public string $balance,            // debit - credit (signed by normal balance)
    ) {
    }
}
