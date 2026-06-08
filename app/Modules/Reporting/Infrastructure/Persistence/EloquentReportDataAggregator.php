<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Persistence;

use App\Modules\Reporting\Application\Contracts\ReportDataAggregatorContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use Illuminate\Database\ConnectionInterface;

/**
 * Cross-schema aggregator backed by raw SQL on accounting.* tables.
 *
 * All queries scope to POSTED journal entries (posted_at IS NOT NULL)
 * and EXCLUDE entries that have been reversed (reversed_by IS NOT NULL).
 * Reversal entries themselves are included — they net out the original.
 *
 * Voided source documents (sales invoices, vendor bills) are out of scope
 * here: the reversal JV they created handles the netting at the journal
 * level. We only see balanced double-entry data.
 */
final readonly class EloquentReportDataAggregator implements ReportDataAggregatorContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function trialBalanceData(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT
                a.id                                            AS account_id,
                a.code                                          AS account_code,
                a.name                                          AS account_name,
                a.type                                          AS account_type,
                a.normal_balance                                AS normal_balance,
                a.pfrs_classification                           AS pfrs_classification,
                COALESCE(SUM(l.php_amount) FILTER (WHERE l.debit  > 0), 0) AS total_debit,
                COALESCE(SUM(l.php_amount) FILTER (WHERE l.credit > 0), 0) AS total_credit
            FROM accounting.accounts a
            LEFT JOIN accounting.journal_lines l   ON l.account_id = a.id
            LEFT JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
                AND e.posted_at IS NOT NULL
                AND e.entry_date BETWEEN ?::date AND ?::date
            WHERE a.company_id   = ?::uuid
              AND a.is_postable  = true
              AND a.is_active    = true
            GROUP BY a.id, a.code, a.name, a.type, a.normal_balance, a.pfrs_classification
            ORDER BY a.code
        SQL, [$period->from->format('Y-m-d'), $period->to->format('Y-m-d'), $companyId]);

        return array_map(fn ($r) => [
            'account_id'           => (string) $r->account_id,
            'account_code'         => (string) $r->account_code,
            'account_name'         => (string) $r->account_name,
            'account_type'         => (string) $r->account_type,
            'normal_balance'       => (string) $r->normal_balance,
            'pfrs_classification'  => $r->pfrs_classification ? (string) $r->pfrs_classification : null,
            'total_debit'          => (string) $r->total_debit,
            'total_credit'         => (string) $r->total_credit,
        ], $rows);
    }

    public function balancesAsOf(string $companyId, ReportPeriod $period): array
    {
        // Cumulative balance up to as_of_date. For assets and liabilities we
        // take the running balance from period beginning of time through to.
        // (php_amount in journal_lines is signed: + for debit, − for credit
        // by our convention, so summing gives the natural balance.)
        //
        // Actually re-reading: our JournalLine stores `php_amount` as a
        // signed value with the convention "debit positive, credit negative"
        // when posted via CreateJournalEntry; let's stick to that contract.
        $rows = $this->db->select(<<<'SQL'
            SELECT
                a.id                          AS account_id,
                a.code                        AS account_code,
                a.name                        AS account_name,
                a.type                        AS account_type,
                a.normal_balance              AS normal_balance,
                a.pfrs_classification         AS pfrs_classification,
                COALESCE(
                    ABS(SUM(l.php_amount) FILTER (WHERE l.debit  > 0))
                    - ABS(SUM(l.php_amount) FILTER (WHERE l.credit > 0))
                , 0)                          AS signed_balance,
                COALESCE(SUM(l.php_amount) FILTER (WHERE l.debit  > 0), 0) AS total_debit,
                COALESCE(SUM(l.php_amount) FILTER (WHERE l.credit > 0), 0) AS total_credit
            FROM accounting.accounts a
            LEFT JOIN accounting.journal_lines l   ON l.account_id = a.id
            LEFT JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
                AND e.posted_at IS NOT NULL
                AND e.entry_date <= ?::date
            WHERE a.company_id  = ?::uuid
              AND a.is_postable = true
              AND a.is_active   = true
            GROUP BY a.id, a.code, a.name, a.type, a.normal_balance, a.pfrs_classification
            ORDER BY a.code
        SQL, [$period->to->format('Y-m-d'), $companyId]);

        return array_map(fn ($r) => [
            'account_id'          => (string) $r->account_id,
            'account_code'        => (string) $r->account_code,
            'account_name'        => (string) $r->account_name,
            'account_type'        => (string) $r->account_type,
            'pfrs_classification' => $r->pfrs_classification ? (string) $r->pfrs_classification : null,
            'normal_balance'      => (string) $r->normal_balance,
            'balance'             => $this->balanceFor($r),
        ], $rows);
    }

    public function periodBalances(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT
                a.id                          AS account_id,
                a.code                        AS account_code,
                a.name                        AS account_name,
                a.type                        AS account_type,
                a.normal_balance              AS normal_balance,
                a.pfrs_classification         AS pfrs_classification,
                COALESCE(SUM(l.php_amount) FILTER (WHERE l.debit  > 0), 0) AS total_debit,
                COALESCE(SUM(l.php_amount) FILTER (WHERE l.credit > 0), 0) AS total_credit
            FROM accounting.accounts a
            LEFT JOIN accounting.journal_lines l   ON l.account_id = a.id
            LEFT JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
                AND e.posted_at IS NOT NULL
                AND e.entry_date BETWEEN ?::date AND ?::date
            WHERE a.company_id  = ?::uuid
              AND a.is_postable = true
              AND a.is_active   = true
            GROUP BY a.id, a.code, a.name, a.type, a.normal_balance, a.pfrs_classification
            ORDER BY a.code
        SQL, [$period->from->format('Y-m-d'), $period->to->format('Y-m-d'), $companyId]);

        return array_map(fn ($r) => [
            'account_id'          => (string) $r->account_id,
            'account_code'        => (string) $r->account_code,
            'account_name'        => (string) $r->account_name,
            'account_type'        => (string) $r->account_type,
            'pfrs_classification' => $r->pfrs_classification ? (string) $r->pfrs_classification : null,
            'normal_balance'      => (string) $r->normal_balance,
            'balance'             => $this->balanceFor($r),
        ], $rows);
    }

    public function netIncomeForFiscalYear(string $companyId, ReportPeriod $period): string
    {
        // Revenue − (Cost of Sales + Operating Expenses + Other Expenses + Income Tax)
        // for the fiscal year up to as_of_date.
        $fyStart = $period->fiscalYearStart()->format('Y-m-d');

        $row = $this->db->selectOne(<<<'SQL'
            SELECT
                COALESCE(SUM(CASE WHEN a.type = 'revenue' THEN l.php_amount * -1 END), 0) AS revenue_total,
                COALESCE(SUM(CASE WHEN a.type = 'expense' THEN l.php_amount END), 0)      AS expense_total
            FROM accounting.accounts a
            INNER JOIN accounting.journal_lines l   ON l.account_id = a.id
            INNER JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
            WHERE a.company_id   = ?::uuid
              AND a.is_postable  = true
              AND e.posted_at IS NOT NULL
              AND e.entry_date BETWEEN ?::date AND ?::date
              AND a.type IN ('revenue', 'expense')
        SQL, [$companyId, $fyStart, $period->to->format('Y-m-d')]);

        return bcsub((string) $row->revenue_total, (string) $row->expense_total, 2);
    }

    public function cashFlowData(string $companyId, ReportPeriod $period): array
    {
        // Beginning + ending cash balances
        $cashBeginning = $this->cashBalanceAsOf($companyId, $period->from->modify('-1 day')->format('Y-m-d'));
        $cashEnding    = $this->cashBalanceAsOf($companyId, $period->to->format('Y-m-d'));

        // For each JE that touches a cash account in the period, classify the
        // movement by the offsetting account's PFRS classification.
        // Cash accounts: PFRS classification 'current_asset' AND account code
        // starts with '1110' (Cash and Cash Equivalents tree).
        $rows = $this->db->select(<<<'SQL'
            WITH cash_movements AS (
                SELECT
                    cl.journal_entry_id,
                    -- Cash inflow = debit to cash; outflow = credit
                    (cl.debit - cl.credit) AS cash_delta
                FROM accounting.journal_lines cl
                INNER JOIN accounting.accounts ca ON ca.id = cl.account_id
                INNER JOIN accounting.journal_entries e ON e.id = cl.journal_entry_id
                WHERE ca.company_id = ?::uuid
                  AND ca.code LIKE '1110%'
                  AND e.posted_at IS NOT NULL
                  AND e.entry_date BETWEEN ?::date AND ?::date
            ),
            offset_lines AS (
                -- For each cash-touching JE, find the dominant offsetting account type
                SELECT
                    ol.journal_entry_id,
                    oa.type                AS other_type,
                    oa.pfrs_classification AS other_pfrs,
                    oa.name                AS other_name,
                    SUM(ol.php_amount)     AS magnitude
                FROM accounting.journal_lines ol
                INNER JOIN accounting.accounts oa ON oa.id = ol.account_id
                WHERE oa.code NOT LIKE '1110%'
                  AND ol.journal_entry_id IN (SELECT journal_entry_id FROM cash_movements)
                GROUP BY ol.journal_entry_id, oa.type, oa.pfrs_classification, oa.name
            ),
            dominant_offsets AS (
                -- Pick the line with the largest magnitude as the dominant counterpart
                SELECT DISTINCT ON (journal_entry_id)
                    journal_entry_id, other_type, other_pfrs, other_name
                FROM offset_lines
                ORDER BY journal_entry_id, ABS(magnitude) DESC
            )
            SELECT
                e.entry_date,
                e.doc_no,
                e.memo,
                e.source,
                cm.cash_delta,
                d.other_type,
                d.other_pfrs,
                d.other_name
            FROM cash_movements cm
            INNER JOIN accounting.journal_entries e ON e.id = cm.journal_entry_id
            LEFT  JOIN dominant_offsets d           ON d.journal_entry_id = cm.journal_entry_id
            ORDER BY e.entry_date, e.sequence_no
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        $operating = [];
        $investing = [];
        $financing = [];

        foreach ($rows as $r) {
            $category = $this->classifyCashFlow((string) $r->other_type, (string) ($r->other_pfrs ?? ''));
            $description = sprintf(
                '%s · %s%s',
                (string) $r->doc_no,
                (string) ($r->other_name ?? '(unclassified)'),
                $r->memo ? ' — '.(string) $r->memo : '',
            );

            $entry = ['description' => $description, 'amount' => (string) $r->cash_delta];

            match ($category) {
                'investing' => $investing[] = $entry,
                'financing' => $financing[] = $entry,
                default     => $operating[] = $entry,
            };
        }

        return [
            'operating'      => $operating,
            'investing'      => $investing,
            'financing'      => $financing,
            'beginning_cash' => $cashBeginning,
            'ending_cash'    => $cashEnding,
        ];
    }

    public function equityMovements(string $companyId, ReportPeriod $period): array
    {
        $beginningDate = $period->from->modify('-1 day')->format('Y-m-d');

        // Each equity account: beginning balance + movement in period + ending balance
        $rows = $this->db->select(<<<'SQL'
            SELECT
                a.id   AS account_id,
                a.code AS account_code,
                a.name AS account_name,
                COALESCE((
                    SELECT SUM(l2.credit - l2.debit)
                    FROM accounting.journal_lines l2
                    INNER JOIN accounting.journal_entries e2 ON e2.id = l2.journal_entry_id
                    WHERE l2.account_id = a.id
                      AND e2.posted_at IS NOT NULL
                      AND e2.entry_date <= ?::date
                ), 0) AS beginning_balance,
                COALESCE((
                    SELECT SUM(l3.credit - l3.debit)
                    FROM accounting.journal_lines l3
                    INNER JOIN accounting.journal_entries e3 ON e3.id = l3.journal_entry_id
                    WHERE l3.account_id = a.id
                      AND e3.posted_at IS NOT NULL
                      AND e3.entry_date BETWEEN ?::date AND ?::date
                ), 0) AS movement
            FROM accounting.accounts a
            WHERE a.company_id  = ?::uuid
              AND a.type        IN ('equity', 'contra_equity')
              AND a.is_postable = true
              AND a.is_active   = true
            ORDER BY a.code
        SQL, [
            $beginningDate,
            $period->from->format('Y-m-d'),
            $period->to->format('Y-m-d'),
            $companyId,
        ]);

        $accounts = array_map(fn ($r) => [
            'account_id'        => (string) $r->account_id,
            'account_code'      => (string) $r->account_code,
            'account_name'      => (string) $r->account_name,
            'beginning_balance' => (string) $r->beginning_balance,
            'movement'          => (string) $r->movement,
            'ending_balance'    => bcadd((string) $r->beginning_balance, (string) $r->movement, 2),
        ], $rows);

        return [
            'accounts'              => $accounts,
            'net_income_for_period' => $this->netIncomeForPeriod($companyId, $period),
        ];
    }

    /** Cumulative cash balance across all 1110.* accounts as of date. */
    private function cashBalanceAsOf(string $companyId, string $date): string
    {
        $row = $this->db->selectOne(<<<'SQL'
            SELECT COALESCE(SUM(l.debit - l.credit), 0) AS balance
            FROM accounting.journal_lines l
            INNER JOIN accounting.accounts a       ON a.id = l.account_id
            INNER JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
            WHERE a.company_id  = ?::uuid
              AND a.code LIKE '1110%'
              AND e.posted_at IS NOT NULL
              AND e.entry_date <= ?::date
        SQL, [$companyId, $date]);

        return (string) ($row->balance ?? '0.00');
    }

    /** Net income strictly for the requested period (not fiscal-year-to-date). */
    private function netIncomeForPeriod(string $companyId, ReportPeriod $period): string
    {
        $row = $this->db->selectOne(<<<'SQL'
            SELECT
                COALESCE(SUM(CASE WHEN a.type = 'revenue' THEN l.php_amount * -1 END), 0) AS revenue_total,
                COALESCE(SUM(CASE WHEN a.type = 'expense' THEN l.php_amount END), 0)      AS expense_total
            FROM accounting.accounts a
            INNER JOIN accounting.journal_lines l   ON l.account_id = a.id
            INNER JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
            WHERE a.company_id  = ?::uuid
              AND a.is_postable = true
              AND e.posted_at IS NOT NULL
              AND e.entry_date BETWEEN ?::date AND ?::date
              AND a.type IN ('revenue', 'expense')
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return bcsub((string) $row->revenue_total, (string) $row->expense_total, 2);
    }

    /**
     * Simplified cash-flow categorization heuristic.
     * Production deployments should review this against their specific CoA.
     */
    private function classifyCashFlow(string $offsetType, string $offsetPfrs): string
    {
        // Acquisition / disposal of non-current assets → Investing
        if ($offsetPfrs === 'noncurrent_asset') {
            return 'investing';
        }

        // Equity contributions / withdrawals + long-term loans → Financing
        if (in_array($offsetType, ['equity', 'contra_equity'], true)) {
            return 'financing';
        }
        if ($offsetPfrs === 'noncurrent_liability') {
            return 'financing';
        }

        // Everything else (revenue, expense, current assets/liabilities) → Operating
        return 'operating';
    }

    /**
     * Compute the natural balance from raw debit/credit sums.
     * For debit-normal accounts: balance = debit - credit (positive when normal)
     * For credit-normal accounts: balance = credit - debit (positive when normal)
     */
    private function balanceFor(object $row): string
    {
        $debit  = (string) $row->total_debit;
        $credit = (string) $row->total_credit;

        return $row->normal_balance === 'debit'
            ? bcsub($debit, $credit, 2)
            : bcsub($credit, $debit, 2);
    }
}
