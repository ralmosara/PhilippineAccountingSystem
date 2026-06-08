<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Services;

use App\Modules\Reporting\Domain\Entities\FinancialStatementLine;
use App\Modules\Reporting\Domain\Entities\IncomeStatement;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Builds an Income Statement (P&L) for a period.
 *
 * Classification:
 *   - operating_revenue → Revenue
 *   - cost_of_sales     → Cost of Sales
 *   - operating_expense → Operating Expenses
 *   - other_income      → Other Income
 *   - other_expense / finance_cost → Other Expenses
 *   - income_tax        → Income Tax
 */
final readonly class IncomeStatementBuilder
{
    /**
     * @param  list<array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     account_type: string,
     *     pfrs_classification: string|null,
     *     normal_balance: string,
     *     balance: string,
     * }>  $rows
     */
    public function build(string $companyId, ReportPeriod $period, array $rows): IncomeStatement
    {
        $is = new IncomeStatement($companyId, $period);

        foreach ($rows as $row) {
            if (bccomp($row['balance'], '0', 2) === 0) {
                continue;
            }

            $line = new FinancialStatementLine(
                accountId:          $row['account_id'],
                accountCode:        $row['account_code'],
                accountName:        $row['account_name'],
                amount:             $row['balance'],
                pfrsClassification: $row['pfrs_classification'],
            );

            match ($row['pfrs_classification']) {
                'operating_revenue' => $is->addRevenue($line),
                'cost_of_sales'     => $is->addCostOfSales($line),
                'operating_expense' => $is->addOperatingExpense($line),
                'other_income'      => $is->addOtherIncome($line),
                'other_expense', 'finance_cost' => $is->addOtherExpense($line),
                'income_tax'        => $is->addIncomeTax($line),
                default             => $this->dispatchByType($is, $line, $row['account_type']),
            };
        }

        return $is;
    }

    private function dispatchByType(IncomeStatement $is, FinancialStatementLine $line, string $type): void
    {
        match ($type) {
            'revenue' => $is->addRevenue($line),
            'expense' => $is->addOperatingExpense($line),
            default   => null,            // assets/liabilities/equity not on IS
        };
    }
}
