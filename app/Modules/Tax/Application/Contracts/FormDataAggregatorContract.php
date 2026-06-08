<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Domain\ValueObjects\FormPeriod;

/**
 * Cross-schema reader. Aggregates raw data from sales.* and procurement.*
 * for BIR form generation. The Tax module's Application layer owns this
 * contract; the Infrastructure layer's `EloquentFormDataAggregator`
 * implements it by querying the other schemas read-only.
 *
 * Crucially, this is the ONLY place Tax touches Sales/Procurement data —
 * everywhere else uses domain entities from those modules' own contracts.
 */
interface FormDataAggregatorContract
{
    /**
     * @return array{
     *     vatable_sales: string,
     *     zero_rated_sales: string,
     *     exempt_sales: string,
     *     output_vat: string,
     *     vat_input: string,
     *     vat_input_capital_goods: string,
     *     prior_excess_input: string,
     *     withheld_vat_on_gov_sales: string,
     *     advance_payments: string,
     * }
     */
    public function aggregateVatReturn(string $companyId, FormPeriod $period): array;

    /**
     * @return list<array{
     *     vendor_bill_id: string,
     *     vendor_id: string,
     *     vendor_tin: string,
     *     vendor_name: string,
     *     atc_code: string,
     *     income_payment: string,
     *     tax_withheld: string,
     *     payment_date: string,
     * }>
     */
    public function listForm2307ForPeriod(string $companyId, FormPeriod $period): array;

    /**
     * Returns the unused input VAT carried over from the prior period
     * (line 22 of the prior 2550M/Q). Used as line 18A of the current
     * return.
     */
    public function priorPeriodExcessInput(string $companyId, FormPeriod $period): string;

    /**
     * Annual per-employee compensation summary for 1604-CF (Schedule 7.1 MWE
     * and 7.2 non-MWE).
     *
     * @return list<array{
     *     employee_id: string,
     *     tin: string,                          // decrypted in aggregator
     *     full_name: string,
     *     is_mwe: bool,
     *     gross_compensation: string,
     *     nontaxable_compensation: string,
     *     taxable_compensation: string,
     *     sss_ee: string,
     *     phic_ee: string,
     *     hdmf_ee: string,
     *     withholding_tax: string,
     * }>
     */
    public function annualEmployeeAlphalist(string $companyId, FormPeriod $period): array;

    /**
     * Annual per-payee EWT summary for 1604-E (Schedule 1).
     * Pulled from tax.form_2307 grouped by vendor × ATC.
     *
     * @return list<array{
     *     vendor_id: string,
     *     tin: string,
     *     registered_name: string,
     *     atc_code: string,
     *     total_income_payment: string,
     *     total_tax_withheld: string,
     * }>
     */
    public function annualPayeeAlphalist(string $companyId, FormPeriod $period): array;
}
