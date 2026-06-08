<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Entities;

use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final class TrialBalance
{
    /** @var array<int, TrialBalanceLine> */
    public array $lines = [];

    public string $totalDebit = '0.00';
    public string $totalCredit = '0.00';

    public function __construct(
        public readonly string $companyId,
        public readonly ReportPeriod $period,
    ) {
    }

    public function addLine(TrialBalanceLine $line): void
    {
        $this->lines[] = $line;
        $this->totalDebit  = bcadd($this->totalDebit,  $line->debit, 2);
        $this->totalCredit = bcadd($this->totalCredit, $line->credit, 2);
    }

    public function isBalanced(): bool
    {
        return bccomp($this->totalDebit, $this->totalCredit, 2) === 0;
    }
}
