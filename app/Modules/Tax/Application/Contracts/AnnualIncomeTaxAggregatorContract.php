<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Domain\ValueObjects\FormPeriod;

/**
 * Aggregates fiscal-year revenue / COGS / OpEx / other income from posted
 * journal entries for BIR Form 1701 (individual) and 1702 (corporate) ITR
 * computation.
 *
 * Owned by Tax (the consumer); implementation queries accounting.* directly.
 * Kept separate from the Reporting module's aggregator because the line-by-line
 * mapping to BIR ITR form lines differs from the PFRS Income Statement.
 */
interface AnnualIncomeTaxAggregatorContract
{
    /**
     * @return array{
     *     gross_revenue: string,
     *     sales_returns: string,
     *     cost_of_sales: string,
     *     operating_expenses: string,
     *     other_income: string,
     *     other_expenses: string,
     *     accrued_income_tax: string,
     *     net_income_before_tax: string,
     * }
     */
    public function fiscalYearTotals(string $companyId, FormPeriod $period): array;

    /**
     * Total assets as of fiscal year-end — used for CREATE Act MSME determination.
     * MSME = total_assets (excluding land) ≤ ₱100M.
     */
    public function totalAssetsAsOf(string $companyId, FormPeriod $period): string;

    /**
     * Sum of creditable withholding tax certificates the company received
     * from its customers (vs ones we ISSUED to vendors, which live in
     * tax.form_2307). Used as line "Creditable WT" credit against tax due.
     */
    public function creditableWithholdingTaxReceived(string $companyId, FormPeriod $period): string;

    /**
     * Sum of `tax_paid` recorded on every prior 1701Q / 1702Q filing in the
     * same fiscal year. Used by the next quarter's filing to subtract
     * "tax already paid YTD" from the cumulative tax due.
     *
     * @param  string  $formType    '1701Q' (individual) or '1702Q' (corporate)
     * @param  int     $upToQuarter exclusive — sums quarters 1..(upToQuarter-1)
     */
    public function priorQuarterTaxPayments(
        string $companyId,
        string $formType,
        int $year,
        int $upToQuarter,
    ): string;
}
