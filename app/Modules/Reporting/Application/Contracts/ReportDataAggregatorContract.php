<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Contracts;

use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Cross-schema aggregator — reads from accounting.* read-only.
 *
 * All queries scope to POSTED journal entries (posted_at IS NOT NULL,
 * NOT reversed by an unposted entry) and respect period boundaries.
 *
 * Implementations may use `pgsql_read` replica connection for offloading.
 */
interface ReportDataAggregatorContract
{
    /**
     * Per-account debit/credit totals within a period.
     *
     * @return list<array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     account_type: string,
     *     normal_balance: string,
     *     pfrs_classification: string|null,
     *     total_debit: string,
     *     total_credit: string,
     * }>
     */
    public function trialBalanceData(string $companyId, ReportPeriod $period): array;

    /**
     * Per-account cumulative balance as of a date (used by Balance Sheet).
     *
     * @return list<array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     account_type: string,
     *     pfrs_classification: string|null,
     *     normal_balance: string,
     *     balance: string,
     * }>
     */
    public function balancesAsOf(string $companyId, ReportPeriod $period): array;

    /**
     * Per-account balance within a period (used by Income Statement).
     *
     * @return list<array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     account_type: string,
     *     pfrs_classification: string|null,
     *     normal_balance: string,
     *     balance: string,
     * }>
     */
    public function periodBalances(string $companyId, ReportPeriod $period): array;

    /**
     * Computes net income for a given period using P&L accounts.
     * Used by Balance Sheet as the "Current Year Earnings" equity line.
     */
    public function netIncomeForFiscalYear(string $companyId, ReportPeriod $period): string;

    /**
     * Cash-affecting JE lines in the period, with each cash movement
     * classified into Operating / Investing / Financing based on the
     * offsetting account's type and PFRS classification.
     *
     * Categorization rules (simplified — direct method with type heuristics):
     *   - Offsetting account is revenue/expense/AR/AP/inventory → operating
     *   - Offsetting account is PFRS noncurrent_asset (PPE etc.)  → investing
     *   - Offsetting account is equity OR noncurrent_liability   → financing
     *
     * @return array{
     *     operating: list<array{description: string, amount: string}>,
     *     investing: list<array{description: string, amount: string}>,
     *     financing: list<array{description: string, amount: string}>,
     *     beginning_cash: string,
     *     ending_cash: string,
     * }
     */
    public function cashFlowData(string $companyId, ReportPeriod $period): array;

    /**
     * Equity component movements during the period — opening balance,
     * activity in period, ending balance per equity account.
     *
     * Plus the period's net income (which feeds Current Year Earnings).
     *
     * @return array{
     *     accounts: list<array{
     *         account_id: string,
     *         account_code: string,
     *         account_name: string,
     *         beginning_balance: string,
     *         movement: string,
     *         ending_balance: string,
     *     }>,
     *     net_income_for_period: string,
     * }
     */
    public function equityMovements(string $companyId, ReportPeriod $period): array;
}
