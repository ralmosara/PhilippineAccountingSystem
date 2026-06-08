<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence;

use App\Modules\Tax\Application\Contracts\AnnualIncomeTaxAggregatorContract;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Database\ConnectionInterface;

final readonly class EloquentAnnualIncomeTaxAggregator implements AnnualIncomeTaxAggregatorContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function fiscalYearTotals(string $companyId, FormPeriod $period): array
    {
        // Aggregate posted-JE lines by PFRS classification + account type
        // for the fiscal year. Revenue lines are sign-flipped because their
        // php_amount is stored credit-negative.
        $row = $this->db->selectOne(<<<'SQL'
            SELECT
                -- Gross revenue: operating_revenue + service revenue
                COALESCE(SUM(
                    CASE
                        WHEN a.pfrs_classification = 'operating_revenue'
                         AND a.normal_balance      = 'credit'
                        THEN l.php_amount * -1
                    END
                ), 0) AS gross_revenue,

                -- Sales returns and discounts (contra-revenue: debit-normal under revenue tree)
                COALESCE(SUM(
                    CASE
                        WHEN a.type             = 'revenue'
                         AND a.normal_balance   = 'debit'
                        THEN l.php_amount
                    END
                ), 0) AS sales_returns,

                -- Cost of sales
                COALESCE(SUM(
                    CASE WHEN a.pfrs_classification = 'cost_of_sales' THEN l.php_amount END
                ), 0) AS cost_of_sales,

                -- Operating expenses
                COALESCE(SUM(
                    CASE WHEN a.pfrs_classification = 'operating_expense' THEN l.php_amount END
                ), 0) AS operating_expenses,

                -- Other income
                COALESCE(SUM(
                    CASE WHEN a.pfrs_classification = 'other_income' THEN l.php_amount * -1 END
                ), 0) AS other_income,

                -- Other expenses (incl. finance_cost)
                COALESCE(SUM(
                    CASE
                        WHEN a.pfrs_classification IN ('other_expense', 'finance_cost')
                        THEN l.php_amount
                    END
                ), 0) AS other_expenses,

                -- Income tax accrued via JEs during the year
                COALESCE(SUM(
                    CASE WHEN a.pfrs_classification = 'income_tax' THEN l.php_amount END
                ), 0) AS accrued_income_tax
            FROM accounting.accounts a
            INNER JOIN accounting.journal_lines l   ON l.account_id = a.id
            INNER JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
            WHERE a.company_id   = ?::uuid
              AND a.is_postable  = true
              AND e.posted_at IS NOT NULL
              AND e.entry_date BETWEEN ?::date AND ?::date
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        $grossRevenue  = (string) $row->gross_revenue;
        $salesReturns  = (string) $row->sales_returns;
        $costOfSales   = (string) $row->cost_of_sales;
        $opEx          = (string) $row->operating_expenses;
        $otherIncome   = (string) $row->other_income;
        $otherExpenses = (string) $row->other_expenses;
        $accruedTax    = (string) $row->accrued_income_tax;

        // Net Income Before Tax = (Gross Revenue − Returns − COGS − OpEx) + Other Income − Other Expenses
        $grossProfit       = bcsub(bcsub($grossRevenue, $salesReturns, 2), $costOfSales, 2);
        $operatingIncome   = bcsub($grossProfit, $opEx, 2);
        $netBeforeTax      = bcsub(bcadd($operatingIncome, $otherIncome, 2), $otherExpenses, 2);

        return [
            'gross_revenue'         => $grossRevenue,
            'sales_returns'         => $salesReturns,
            'cost_of_sales'         => $costOfSales,
            'operating_expenses'    => $opEx,
            'other_income'          => $otherIncome,
            'other_expenses'        => $otherExpenses,
            'accrued_income_tax'    => $accruedTax,
            'net_income_before_tax' => $netBeforeTax,
        ];
    }

    public function totalAssetsAsOf(string $companyId, FormPeriod $period): string
    {
        $row = $this->db->selectOne(<<<'SQL'
            SELECT COALESCE(SUM(
                CASE
                    WHEN a.type = 'asset'        THEN l.php_amount
                    WHEN a.type = 'contra_asset' THEN -l.php_amount
                END
            ), 0) AS total_assets
            FROM accounting.accounts a
            INNER JOIN accounting.journal_lines l   ON l.account_id = a.id
            INNER JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
            WHERE a.company_id  = ?::uuid
              AND a.is_postable = true
              AND a.type IN ('asset', 'contra_asset')
              AND e.posted_at IS NOT NULL
              AND e.entry_date <= ?::date
              -- Exclude land per CREATE Act MSME criteria
              AND a.code NOT LIKE '1210-001%'
        SQL, [$companyId, $period->to->format('Y-m-d')]);

        return (string) $row->total_assets;
    }

    public function priorQuarterTaxPayments(
        string $companyId,
        string $formType,
        int $year,
        int $upToQuarter,
    ): string {
        if (! in_array($formType, ['1701Q', '1702Q'], true)) {
            throw new \InvalidArgumentException("priorQuarterTaxPayments: unexpected form type '{$formType}'");
        }
        if ($upToQuarter < 1 || $upToQuarter > 4) {
            throw new \InvalidArgumentException("priorQuarterTaxPayments: invalid quarter {$upToQuarter}");
        }
        if ($upToQuarter === 1) {
            return '0.00';
        }

        // Sums tax_paid on every PRIOR quarter filing for this year+formType.
        // 'generated' and 'filed' both count — the moment a quarterly Q-return
        // is generated, the user is expected to pay BIR against it; including
        // 'generated' ensures successive Q-runs don't double-charge.
        $row = $this->db->selectOne(<<<'SQL'
            SELECT COALESCE(SUM(tax_paid), 0) AS total
              FROM tax.bir_forms
             WHERE company_id = ?::uuid
               AND form_type  = ?
               AND year       = ?
               AND quarter   IS NOT NULL
               AND quarter    < ?
               AND status     IN ('generated', 'filed')
        SQL, [$companyId, $formType, $year, $upToQuarter]);

        return (string) $row->total;
    }

    public function creditableWithholdingTaxReceived(string $companyId, FormPeriod $period): string
    {
        // Sums tax_withheld on every 2307 received from a payor during the
        // period, EXCLUDING certs already claimed in another filing and
        // those rejected during review.
        //
        // The "recorded" status means: row exists, not yet claimed in any
        // 1701/1702/1701Q form, not rejected. Those are exactly the certs
        // that should reduce this year's tax due.
        $row = $this->db->selectOne(<<<'SQL'
            SELECT COALESCE(SUM(tax_withheld), 0) AS total
              FROM tax.form_2307_received
             WHERE company_id  = ?::uuid
               AND status      = 'recorded'
               AND period_to BETWEEN ?::date AND ?::date
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return (string) $row->total;
    }
}
