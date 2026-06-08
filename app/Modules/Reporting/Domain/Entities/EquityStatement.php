<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Entities;

use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Statement of Changes in Equity (PFRS Section 6 / PFRS for SMEs Section 6).
 *
 * Tracks beginning balance + movement + ending balance per equity component,
 * plus the period's net income (Current Year Earnings).
 */
final class EquityStatement
{
    /**
     * @var list<array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     beginning_balance: string,
     *     movement: string,
     *     ending_balance: string,
     * }>
     */
    public array $accounts = [];

    public string $totalBeginning = '0.00';
    public string $totalMovement  = '0.00';
    public string $totalEnding    = '0.00';

    public string $netIncomeForPeriod = '0.00';

    public function __construct(
        public readonly string $companyId,
        public readonly ReportPeriod $period,
    ) {
    }

    /**
     * @param array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     beginning_balance: string,
     *     movement: string,
     *     ending_balance: string,
     * } $row
     */
    public function addAccount(array $row): void
    {
        $this->accounts[] = $row;
        $this->totalBeginning = bcadd($this->totalBeginning, $row['beginning_balance'], 2);
        $this->totalMovement  = bcadd($this->totalMovement,  $row['movement'],          2);
        $this->totalEnding    = bcadd($this->totalEnding,    $row['ending_balance'],    2);
    }

    public function setNetIncome(string $amount): void
    {
        $this->netIncomeForPeriod = $amount;
    }

    /** Total Equity = sum of all components + Current Year Earnings. */
    public function totalEquity(): string
    {
        return bcadd($this->totalEnding, $this->netIncomeForPeriod, 2);
    }
}
