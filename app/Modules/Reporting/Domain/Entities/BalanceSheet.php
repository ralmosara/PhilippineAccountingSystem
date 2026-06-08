<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Entities;

use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final class BalanceSheet
{
    /** @var array<string, list<FinancialStatementLine>> Keyed by PFRS classification */
    public array $sections = [
        'current_asset'         => [],
        'noncurrent_asset'      => [],
        'current_liability'     => [],
        'noncurrent_liability'  => [],
        'equity'                => [],
    ];

    public string $totalAssets       = '0.00';
    public string $totalLiabilities  = '0.00';
    public string $totalEquity       = '0.00';
    public string $currentYearEarnings = '0.00';     // Net Income flowing into equity

    public function __construct(
        public readonly string $companyId,
        public readonly ReportPeriod $period,
    ) {
    }

    public function addLine(string $section, FinancialStatementLine $line): void
    {
        $this->sections[$section][] = $line;

        if (str_ends_with($section, 'asset')) {
            $this->totalAssets = bcadd($this->totalAssets, $line->amount, 2);
        } elseif (str_ends_with($section, 'liability')) {
            $this->totalLiabilities = bcadd($this->totalLiabilities, $line->amount, 2);
        } elseif ($section === 'equity') {
            $this->totalEquity = bcadd($this->totalEquity, $line->amount, 2);
        }
    }

    public function setCurrentYearEarnings(string $amount): void
    {
        $this->currentYearEarnings = $amount;
        $this->totalEquity = bcadd($this->totalEquity, $amount, 2);
    }

    /** Total Liabilities + Equity (should equal Total Assets). */
    public function totalLiabilitiesAndEquity(): string
    {
        return bcadd($this->totalLiabilities, $this->totalEquity, 2);
    }

    public function isBalanced(): bool
    {
        return bccomp($this->totalAssets, $this->totalLiabilitiesAndEquity(), 2) === 0;
    }
}
