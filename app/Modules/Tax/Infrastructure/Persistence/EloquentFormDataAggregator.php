<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence;

use App\Modules\Tax\Application\Contracts\FormDataAggregatorContract;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Database\ConnectionInterface;

/**
 * Reads aggregated VAT and withholding data from sales/procurement schemas
 * for BIR form generation.
 *
 * Crucially: this is the *only* place in the Tax module that touches
 * the sales.* and procurement.* schemas. Domain/Application code
 * receives shaped DTOs and never sees Eloquent or raw SQL.
 *
 * All queries run through the read-replica connection (`pgsql_read`)
 * configured in config/database.php so heavy form generation doesn't
 * compete with transactional writes.
 */
final readonly class EloquentFormDataAggregator implements FormDataAggregatorContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function aggregateVatReturn(string $companyId, FormPeriod $period): array
    {
        // Sales side — sum from posted, non-voided sales invoices in the period
        $salesAgg = $this->db->selectOne(<<<'SQL'
            SELECT
                COALESCE(SUM(vatable_sales),         0) AS vatable_sales,
                COALESCE(SUM(vat_zero_rated_sales),  0) AS zero_rated_sales,
                COALESCE(SUM(vat_exempt_sales),      0) AS exempt_sales,
                COALESCE(SUM(vat_amount),            0) AS output_vat,
                COALESCE(SUM(withheld_vat),          0) AS withheld_vat_on_gov_sales
            FROM sales.sales_invoices
            WHERE company_id = ?::uuid
              AND posted_at IS NOT NULL
              AND voided_at IS NULL
              AND invoice_date BETWEEN ?::date AND ?::date
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        // Purchases side — sum input VAT (creditable + deferred capital goods)
        $purchaseAgg = $this->db->selectOne(<<<'SQL'
            SELECT
                COALESCE(SUM(vat_input),          0) AS vat_input,
                COALESCE(SUM(vat_input_deferred), 0) AS vat_input_capital_goods
            FROM procurement.vendor_bills
            WHERE company_id = ?::uuid
              AND posted_at IS NOT NULL
              AND voided_at IS NULL
              AND bill_date BETWEEN ?::date AND ?::date
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return [
            'vatable_sales'             => (string) $salesAgg->vatable_sales,
            'zero_rated_sales'          => (string) $salesAgg->zero_rated_sales,
            'exempt_sales'              => (string) $salesAgg->exempt_sales,
            'output_vat'                => (string) $salesAgg->output_vat,
            'vat_input'                 => (string) $purchaseAgg->vat_input,
            'vat_input_capital_goods'   => (string) $purchaseAgg->vat_input_capital_goods,
            'prior_excess_input'        => '0.00',                                                  // populated by caller
            'withheld_vat_on_gov_sales' => (string) $salesAgg->withheld_vat_on_gov_sales,
            'advance_payments'          => '0.00',
        ];
    }

    public function listForm2307ForPeriod(string $companyId, FormPeriod $period): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT
                f.id              AS form_2307_id,
                f.vendor_bill_id  AS vendor_bill_id,
                f.vendor_id       AS vendor_id,
                v.tin             AS vendor_tin,
                v.registered_name AS vendor_name,
                f.atc_code        AS atc_code,
                f.income_payment  AS income_payment,
                f.tax_withheld    AS tax_withheld,
                f.period_from     AS payment_date
            FROM tax.form_2307 f
            INNER JOIN procurement.vendors v ON v.id = f.vendor_id
            WHERE f.company_id = ?::uuid
              AND f.period_from >= ?::date
              AND f.period_to   <= ?::date
            ORDER BY f.period_from, f.atc_code
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return array_map(fn ($r) => [
            'vendor_bill_id'  => (string) $r->vendor_bill_id,
            'vendor_id'       => (string) $r->vendor_id,
            'vendor_tin'      => (string) ($r->vendor_tin ?? ''),
            'vendor_name'     => (string) $r->vendor_name,
            'atc_code'        => (string) $r->atc_code,
            'income_payment'  => (string) $r->income_payment,
            'tax_withheld'    => (string) $r->tax_withheld,
            'payment_date'    => (string) $r->payment_date,
        ], $rows);
    }

    public function priorPeriodExcessInput(string $companyId, FormPeriod $period): string
    {
        // Look up line "22" (Net VAT Payable / Excess Input) from the prior 2550M/Q
        $row = $this->db->selectOne(<<<'SQL'
            SELECT bfl.amount
            FROM tax.bir_forms bf
            INNER JOIN tax.bir_form_lines bfl ON bfl.bir_form_id = bf.id
            WHERE bf.company_id = ?::uuid
              AND bf.form_type IN ('2550M', '2550Q')
              AND bf.period_to < ?::date
              AND bfl.line_code = '22'
            ORDER BY bf.period_to DESC
            LIMIT 1
        SQL, [$companyId, $period->from->format('Y-m-d')]);

        if (! $row) {
            return '0.00';
        }

        // Excess input is when line 22 is NEGATIVE (output_vat < input_vat).
        // Return the absolute value as the carryover; if positive, return 0.
        $amount = (string) $row->amount;
        return bccomp($amount, '0', 2) < 0 ? bcmul($amount, '-1', 2) : '0.00';
    }

    public function annualEmployeeAlphalist(string $companyId, FormPeriod $period): array
    {
        // Aggregate APPROVED payroll runs in the year per employee
        $rows = $this->db->select(<<<'SQL'
            SELECT
                e.id                                AS employee_id,
                e.tin_encrypted                     AS tin_enc,
                e.last_name, e.first_name, e.middle_name, e.suffix,
                COALESCE(SUM(p.gross_compensation),       0) AS gross_compensation,
                COALESCE(SUM(p.nontaxable_compensation),  0) AS nontaxable_compensation,
                COALESCE(SUM(p.taxable_compensation),     0) AS taxable_compensation,
                COALESCE(SUM(p.sss_ee),  0)          AS sss_ee,
                COALESCE(SUM(p.phic_ee), 0)          AS phic_ee,
                COALESCE(SUM(p.hdmf_ee), 0)          AS hdmf_ee,
                COALESCE(SUM(p.withholding_tax), 0)  AS withholding_tax,
                BOOL_OR(COALESCE(cp.is_minimum_wage_earner, FALSE)) AS is_mwe
            FROM payroll.payslips p
            INNER JOIN payroll.payroll_runs    r  ON r.id  = p.payroll_run_id
            INNER JOIN payroll.payroll_periods pp ON pp.id = r.payroll_period_id
            INNER JOIN hr.employees            e  ON e.id  = p.employee_id
            LEFT JOIN LATERAL (
                SELECT is_minimum_wage_earner
                FROM payroll.compensation_packages cp2
                WHERE cp2.employee_id = e.id
                  AND cp2.effective_from <= pp.period_end
                  AND (cp2.effective_to IS NULL OR cp2.effective_to >= pp.period_end)
                ORDER BY cp2.effective_from DESC
                LIMIT 1
            ) cp ON TRUE
            WHERE pp.company_id = ?::uuid
              AND r.approved_at IS NOT NULL
              AND pp.period_start >= ?::date
              AND pp.period_end   <= ?::date
            GROUP BY e.id, e.tin_encrypted, e.last_name, e.first_name, e.middle_name, e.suffix
            ORDER BY e.last_name, e.first_name
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return array_map(function ($r) {
            $tin = '';
            if ($r->tin_enc) {
                try {
                    $tin = \Illuminate\Support\Facades\Crypt::decryptString((string) $r->tin_enc);
                } catch (\Throwable) {
                    $tin = '';
                }
            }
            $fullName = trim(sprintf(
                '%s, %s%s%s',
                $r->last_name,
                $r->first_name,
                $r->middle_name ? ' '.$r->middle_name : '',
                $r->suffix ? ' '.$r->suffix : '',
            ));

            return [
                'employee_id'             => (string) $r->employee_id,
                'tin'                     => $tin,
                'full_name'               => $fullName,
                'is_mwe'                  => (bool) $r->is_mwe,
                'gross_compensation'      => (string) $r->gross_compensation,
                'nontaxable_compensation' => (string) $r->nontaxable_compensation,
                'taxable_compensation'    => (string) $r->taxable_compensation,
                'sss_ee'                  => (string) $r->sss_ee,
                'phic_ee'                 => (string) $r->phic_ee,
                'hdmf_ee'                 => (string) $r->hdmf_ee,
                'withholding_tax'         => (string) $r->withholding_tax,
            ];
        }, $rows);
    }

    public function annualPayeeAlphalist(string $companyId, FormPeriod $period): array
    {
        // 2307s we ISSUED during the year, grouped by vendor × ATC
        $rows = $this->db->select(<<<'SQL'
            SELECT
                v.id                          AS vendor_id,
                v.tin,
                v.registered_name,
                f.atc_code,
                COALESCE(SUM(f.income_payment), 0) AS total_income_payment,
                COALESCE(SUM(f.tax_withheld),   0) AS total_tax_withheld
            FROM tax.form_2307 f
            INNER JOIN procurement.vendors v ON v.id = f.vendor_id
            WHERE f.company_id = ?::uuid
              AND f.period_from >= ?::date
              AND f.period_to   <= ?::date
            GROUP BY v.id, v.tin, v.registered_name, f.atc_code
            ORDER BY v.registered_name, f.atc_code
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return array_map(fn ($r) => [
            'vendor_id'            => (string) $r->vendor_id,
            'tin'                  => $r->tin ? (string) $r->tin : '',
            'registered_name'      => (string) $r->registered_name,
            'atc_code'             => (string) $r->atc_code,
            'total_income_payment' => (string) $r->total_income_payment,
            'total_tax_withheld'   => (string) $r->total_tax_withheld,
        ], $rows);
    }
}
