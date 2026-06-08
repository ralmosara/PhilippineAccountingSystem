<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Services;

use App\Modules\Reporting\Domain\Entities\BalanceSheet;
use App\Modules\Reporting\Domain\Entities\FinancialStatementLine;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Builds a Balance Sheet from aggregated trial-balance-like data:
 *
 *   For asset/liability/equity rows: balance is cumulative through the
 *   `as_of_date`.
 *
 *   Revenue and expense rows are NOT included as separate sections — they
 *   collapse into a single "Current Year Earnings" equity line representing
 *   the cumulative net income for the fiscal year up to `as_of_date`.
 *
 *   Contra-accounts (allowance for doubtful accounts, accumulated
 *   depreciation) are added with their proper sign: their normal_balance
 *   is opposite to the parent classification, so the aggregator already
 *   surfaces them as the right sign.
 */
final readonly class BalanceSheetBuilder
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
    public function build(string $companyId, ReportPeriod $period, array $rows, string $currentYearEarnings): BalanceSheet
    {
        $bs = new BalanceSheet($companyId, $period);

        foreach ($rows as $row) {
            if (bccomp($row['balance'], '0', 2) === 0) {
                continue;
            }

            // Determine balance sheet section from PFRS classification (preferred)
            // or fall back to account.type if classification not seeded.
            $section = $row['pfrs_classification'] ?? $this->fallbackSection($row['account_type']);
            if (! isset($bs->sections[$section])) {
                continue;        // skip P&L accounts — they roll into current_year_earnings
            }

            $bs->addLine($section, new FinancialStatementLine(
                accountId:          $row['account_id'],
                accountCode:        $row['account_code'],
                accountName:        $row['account_name'],
                amount:             $row['balance'],
                pfrsClassification: $row['pfrs_classification'],
            ));
        }

        $bs->setCurrentYearEarnings($currentYearEarnings);

        return $bs;
    }

    private function fallbackSection(string $accountType): string
    {
        return match ($accountType) {
            'asset', 'contra_asset'         => 'current_asset',
            'liability', 'contra_liability' => 'current_liability',
            'equity', 'contra_equity'       => 'equity',
            default                          => 'skip',
        };
    }
}
