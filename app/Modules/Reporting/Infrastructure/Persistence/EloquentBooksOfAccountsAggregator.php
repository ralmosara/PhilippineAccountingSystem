<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Persistence;

use App\Modules\Reporting\Application\Contracts\BooksOfAccountsAggregatorContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use Illuminate\Database\ConnectionInterface;

final readonly class EloquentBooksOfAccountsAggregator implements BooksOfAccountsAggregatorContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function generalJournalEntries(string $companyId, ReportPeriod $period): array
    {
        $entries = $this->db->select(<<<'SQL'
            SELECT id, doc_no, entry_date, source, memo
            FROM accounting.journal_entries
            WHERE company_id = ?::uuid
              AND posted_at IS NOT NULL
              AND entry_date BETWEEN ?::date AND ?::date
            ORDER BY entry_date, sequence_no
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        if ($entries === []) {
            return [];
        }

        $entryIds = array_column($entries, 'id');
        $placeholders = implode(',', array_fill(0, count($entryIds), '?::uuid'));

        $lines = $this->db->select(<<<SQL
            SELECT l.journal_entry_id, l.line_no, a.code AS account_code, a.name AS account_name,
                   l.debit, l.credit, l.memo
            FROM accounting.journal_lines l
            INNER JOIN accounting.accounts a ON a.id = l.account_id
            WHERE l.journal_entry_id IN ({$placeholders})
            ORDER BY l.journal_entry_id, l.line_no
        SQL, $entryIds);

        $linesByEntry = [];
        foreach ($lines as $l) {
            $linesByEntry[$l->journal_entry_id][] = [
                'account_code' => (string) $l->account_code,
                'account_name' => (string) $l->account_name,
                'debit'        => (string) $l->debit,
                'credit'       => (string) $l->credit,
                'memo'         => $l->memo ? (string) $l->memo : null,
            ];
        }

        return array_map(fn ($e) => [
            'entry_id'   => (string) $e->id,
            'doc_no'     => (string) $e->doc_no,
            'entry_date' => (string) $e->entry_date,
            'source'     => (string) $e->source,
            'memo'       => $e->memo ? (string) $e->memo : null,
            'lines'      => $linesByEntry[$e->id] ?? [],
        ], $entries);
    }

    public function generalLedgerByAccount(string $companyId, ReportPeriod $period, ?string $accountId = null): array
    {
        // 1. Get accounts (optionally filtered to one)
        $accountSql = 'SELECT id, code, name, normal_balance FROM accounting.accounts '
                    .'WHERE company_id = ?::uuid AND is_postable = true AND is_active = true';
        $accountParams = [$companyId];

        if ($accountId !== null) {
            $accountSql .= ' AND id = ?::uuid';
            $accountParams[] = $accountId;
        }
        $accountSql .= ' ORDER BY code';

        $accounts = $this->db->select($accountSql, $accountParams);
        if ($accounts === []) {
            return [];
        }

        // 2. Opening balances (transactions before period start)
        $accountIds = array_column($accounts, 'id');
        $placeholders = implode(',', array_fill(0, count($accountIds), '?::uuid'));
        $openings = $this->db->select(<<<SQL
            SELECT l.account_id,
                   COALESCE(SUM(l.debit),  0) - COALESCE(SUM(l.credit), 0) AS net
            FROM accounting.journal_lines l
            INNER JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
            WHERE l.account_id IN ({$placeholders})
              AND e.posted_at IS NOT NULL
              AND e.entry_date < ?::date
            GROUP BY l.account_id
        SQL, [...$accountIds, $period->from->format('Y-m-d')]);

        $openingByAccount = [];
        foreach ($openings as $o) {
            $openingByAccount[$o->account_id] = (string) $o->net;
        }

        // 3. Transactions in period
        $transactions = $this->db->select(<<<SQL
            SELECT l.account_id, e.entry_date, e.doc_no, l.memo,
                   l.debit, l.credit, l.line_no
            FROM accounting.journal_lines l
            INNER JOIN accounting.journal_entries e ON e.id = l.journal_entry_id
            WHERE l.account_id IN ({$placeholders})
              AND e.posted_at IS NOT NULL
              AND e.entry_date BETWEEN ?::date AND ?::date
            ORDER BY l.account_id, e.entry_date, e.sequence_no, l.line_no
        SQL, [...$accountIds, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        $txByAccount = [];
        foreach ($transactions as $t) {
            $txByAccount[$t->account_id][] = $t;
        }

        // 4. Build per-account ledgers with running balances
        $result = [];
        foreach ($accounts as $a) {
            $rawOpening = $openingByAccount[$a->id] ?? '0';
            $opening = $a->normal_balance === 'debit'
                ? $rawOpening
                : bcmul($rawOpening, '-1', 2);

            $running = $opening;
            $txList = [];

            foreach ($txByAccount[$a->id] ?? [] as $t) {
                $delta = bcsub((string) $t->debit, (string) $t->credit, 2);
                $signedDelta = $a->normal_balance === 'debit'
                    ? $delta
                    : bcmul($delta, '-1', 2);
                $running = bcadd($running, $signedDelta, 2);

                $txList[] = [
                    'entry_date'      => (string) $t->entry_date,
                    'doc_no'          => (string) $t->doc_no,
                    'memo'            => $t->memo ? (string) $t->memo : null,
                    'debit'           => (string) $t->debit,
                    'credit'          => (string) $t->credit,
                    'running_balance' => $running,
                ];
            }

            $result[] = [
                'account_id'      => (string) $a->id,
                'account_code'    => (string) $a->code,
                'account_name'    => (string) $a->name,
                'normal_balance'  => (string) $a->normal_balance,
                'opening_balance' => $opening,
                'transactions'    => $txList,
                'closing_balance' => $running,
            ];
        }

        return $result;
    }

    public function salesBookRows(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT i.doc_no, i.invoice_date,
                   c.registered_name AS customer_name, c.tin AS customer_tin,
                   i.vatable_sales, i.vat_zero_rated_sales, i.vat_exempt_sales,
                   i.vat_amount, i.senior_pwd_discount, i.withheld_vat, i.total
            FROM sales.sales_invoices i
            INNER JOIN sales.customers c ON c.id = i.customer_id
            WHERE i.company_id = ?::uuid
              AND i.posted_at IS NOT NULL
              AND i.voided_at IS NULL
              AND i.invoice_date BETWEEN ?::date AND ?::date
            ORDER BY i.invoice_date, i.sequence_no
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return array_map(fn ($r) => [
            'doc_no'               => (string) $r->doc_no,
            'invoice_date'         => (string) $r->invoice_date,
            'customer_name'        => (string) $r->customer_name,
            'customer_tin'         => $r->customer_tin ? (string) $r->customer_tin : null,
            'vatable_sales'        => (string) $r->vatable_sales,
            'vat_zero_rated_sales' => (string) $r->vat_zero_rated_sales,
            'vat_exempt_sales'     => (string) $r->vat_exempt_sales,
            'vat_amount'           => (string) $r->vat_amount,
            'senior_pwd_discount'  => (string) $r->senior_pwd_discount,
            'withheld_vat'         => (string) $r->withheld_vat,
            'total'                => (string) $r->total,
        ], $rows);
    }

    public function purchasesBookRows(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT b.vendor_invoice_no, b.bill_date,
                   v.registered_name AS vendor_name, v.tin AS vendor_tin,
                   b.subtotal, b.vat_input,
                   b.withholding_amount, b.withholding_atc_code, b.total
            FROM procurement.vendor_bills b
            INNER JOIN procurement.vendors v ON v.id = b.vendor_id
            WHERE b.company_id = ?::uuid
              AND b.posted_at IS NOT NULL
              AND b.voided_at IS NULL
              AND b.bill_date BETWEEN ?::date AND ?::date
            ORDER BY b.bill_date, b.created_at
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return array_map(fn ($r) => [
            'vendor_invoice_no'    => (string) $r->vendor_invoice_no,
            'bill_date'            => (string) $r->bill_date,
            'vendor_name'          => (string) $r->vendor_name,
            'vendor_tin'           => $r->vendor_tin ? (string) $r->vendor_tin : null,
            'subtotal'             => (string) $r->subtotal,
            'vat_input'            => (string) $r->vat_input,
            'withholding_amount'   => (string) $r->withholding_amount,
            'withholding_atc_code' => $r->withholding_atc_code ? (string) $r->withholding_atc_code : null,
            'total'                => (string) $r->total,
        ], $rows);
    }

    public function cashReceiptsRows(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT o.doc_no, o.received_date,
                   c.registered_name AS customer_name,
                   o.amount, o.payment_method, o.reference_no
            FROM sales.official_receipts o
            INNER JOIN sales.customers c ON c.id = o.customer_id
            WHERE o.company_id = ?::uuid
              AND o.voided_at IS NULL
              AND o.received_date BETWEEN ?::date AND ?::date
            ORDER BY o.received_date, o.sequence_no
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return array_map(fn ($r) => [
            'doc_no'         => (string) $r->doc_no,
            'received_date'  => (string) $r->received_date,
            'customer_name'  => (string) $r->customer_name,
            'amount'         => (string) $r->amount,
            'payment_method' => (string) $r->payment_method,
            'reference_no'   => $r->reference_no ? (string) $r->reference_no : null,
        ], $rows);
    }

    public function cashDisbursementsRows(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT pv.cv_no, pv.payment_date,
                   COALESCE(string_agg(DISTINCT v.registered_name, ', '), '—') AS payee_name,
                   pv.total_amount AS amount, pv.payment_method, pv.reference_no
            FROM procurement.payment_vouchers pv
            LEFT JOIN procurement.payment_voucher_lines pvl ON pvl.payment_voucher_id = pv.id
            LEFT JOIN procurement.vendor_bills b           ON b.id  = pvl.vendor_bill_id
            LEFT JOIN procurement.vendors v                ON v.id  = b.vendor_id
            WHERE pv.company_id = ?::uuid
              AND pv.payment_date BETWEEN ?::date AND ?::date
            GROUP BY pv.id, pv.cv_no, pv.payment_date, pv.total_amount, pv.payment_method, pv.reference_no
            ORDER BY pv.payment_date, pv.sequence_no
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return array_map(fn ($r) => [
            'cv_no'          => (string) $r->cv_no,
            'payment_date'   => (string) $r->payment_date,
            'payee_name'     => (string) $r->payee_name,
            'amount'         => (string) $r->amount,
            'payment_method' => (string) $r->payment_method,
            'reference_no'   => $r->reference_no ? (string) $r->reference_no : null,
        ], $rows);
    }
}
