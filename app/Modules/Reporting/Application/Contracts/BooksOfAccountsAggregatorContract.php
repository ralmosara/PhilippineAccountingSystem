<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Contracts;

use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Cross-schema aggregator for BIR-mandated Books of Accounts (CAS — RR 9-2009).
 *
 * Six books:
 *   - General Journal           — all posted JEs chronologically (accounting)
 *   - General Ledger            — per-account ledger with running balance (accounting)
 *   - Sales Book                — all posted, non-voided sales invoices (sales)
 *   - Purchases Book            — all posted, non-voided vendor bills (procurement)
 *   - Cash Receipts Book        — all non-voided official receipts (sales)
 *   - Cash Disbursements Book   — all payment vouchers (procurement)
 *
 * The aggregator is the ONLY place in the Reporting module that reads from
 * sales.* and procurement.* tables — keeps bounded contexts clean.
 */
interface BooksOfAccountsAggregatorContract
{
    /**
     * Posted JE headers with line breakdown for chronological listing.
     *
     * @return list<array{
     *     entry_id: string,
     *     doc_no: string,
     *     entry_date: string,
     *     source: string,
     *     memo: string|null,
     *     lines: list<array{account_code: string, account_name: string, debit: string, credit: string, memo: string|null}>,
     * }>
     */
    public function generalJournalEntries(string $companyId, ReportPeriod $period): array;

    /**
     * Per-account transaction list with running balance. If $accountId is null,
     * returns rows for ALL postable accounts.
     *
     * @return list<array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     normal_balance: string,
     *     opening_balance: string,
     *     transactions: list<array{
     *         entry_date: string,
     *         doc_no: string,
     *         memo: string|null,
     *         debit: string,
     *         credit: string,
     *         running_balance: string,
     *     }>,
     *     closing_balance: string,
     * }>
     */
    public function generalLedgerByAccount(string $companyId, ReportPeriod $period, ?string $accountId = null): array;

    /**
     * All posted, non-voided sales invoices in the period.
     *
     * @return list<array{
     *     doc_no: string,
     *     invoice_date: string,
     *     customer_name: string,
     *     customer_tin: string|null,
     *     vatable_sales: string,
     *     vat_zero_rated_sales: string,
     *     vat_exempt_sales: string,
     *     vat_amount: string,
     *     senior_pwd_discount: string,
     *     withheld_vat: string,
     *     total: string,
     * }>
     */
    public function salesBookRows(string $companyId, ReportPeriod $period): array;

    /**
     * All posted, non-voided vendor bills in the period.
     *
     * @return list<array{
     *     vendor_invoice_no: string,
     *     bill_date: string,
     *     vendor_name: string,
     *     vendor_tin: string|null,
     *     subtotal: string,
     *     vat_input: string,
     *     withholding_amount: string,
     *     withholding_atc_code: string|null,
     *     total: string,
     * }>
     */
    public function purchasesBookRows(string $companyId, ReportPeriod $period): array;

    /**
     * All non-voided official receipts in the period.
     *
     * @return list<array{
     *     doc_no: string,
     *     received_date: string,
     *     customer_name: string,
     *     amount: string,
     *     payment_method: string,
     *     reference_no: string|null,
     * }>
     */
    public function cashReceiptsRows(string $companyId, ReportPeriod $period): array;

    /**
     * All payment vouchers in the period.
     *
     * @return list<array{
     *     cv_no: string,
     *     payment_date: string,
     *     payee_name: string,
     *     amount: string,
     *     payment_method: string,
     *     reference_no: string|null,
     * }>
     */
    public function cashDisbursementsRows(string $companyId, ReportPeriod $period): array;
}
