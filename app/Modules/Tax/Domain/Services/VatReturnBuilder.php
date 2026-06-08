<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;

/**
 * Builds the line-level data for BIR Form 2550M / 2550Q (VAT Return).
 *
 * Inputs (provided by FormDataAggregator):
 *   - sales:    vatable_sales, vat_zero_rated_sales, vat_exempt_sales, output_vat
 *   - purchases: vat_input (creditable), vat_input_deferred, capital_goods
 *   - prior period:  excess_input_carry_over
 *
 * Output (BIR-mandated line codes):
 *   1A — Vatable Sales
 *   1B — Output Tax (12% × Vatable Sales)
 *   2  — Zero-Rated Sales
 *   3  — Exempt Sales
 *   8  — Total Output Tax Due
 *  18A — Input Tax Carried Over (from prior period)
 *  18B — Input Tax on Domestic Purchases of Goods Other Than Capital Goods
 *  18C — Input Tax on Importation of Goods Other Than Capital Goods
 *  19  — Total Available Input Tax
 *  21  — Total Allowable Input Tax
 *  22  — Net VAT Payable / (Excess Input Tax)
 *  23A — VAT Withheld on Sales to Government
 *  23B — Total Tax Credits / Payments
 *  24  — Tax Still Due / (Overpayment)
 */
final readonly class VatReturnBuilder
{
    public const VAT_RATE = '0.12';

    /**
     * @param  array{
     *     vatable_sales: string,
     *     zero_rated_sales: string,
     *     exempt_sales: string,
     *     output_vat: string,
     *     vat_input: string,
     *     vat_input_capital_goods: string,
     *     prior_excess_input: string,
     *     withheld_vat_on_gov_sales: string,
     *     advance_payments: string,
     * }  $aggregates
     */
    public function build(BirForm $form, array $aggregates): BirForm
    {
        $taxDue = $this->computeTaxDue($aggregates);
        $taxAvailableCredits = bcadd(
            $aggregates['withheld_vat_on_gov_sales'],
            $aggregates['advance_payments'],
            2,
        );
        $netDue = bcsub($taxDue, $taxAvailableCredits, 2);

        $lines = [
            new BirFormLine('1A',  'Vatable Sales',                   $aggregates['vatable_sales']),
            new BirFormLine('1B',  'Output Tax (12% of 1A)',          $aggregates['output_vat']),
            new BirFormLine('2',   'Zero-Rated Sales',                $aggregates['zero_rated_sales']),
            new BirFormLine('3',   'Exempt Sales',                    $aggregates['exempt_sales']),
            new BirFormLine('8',   'Total Output Tax Due',            $aggregates['output_vat']),

            new BirFormLine('18A', 'Input Tax Carried Over',          $aggregates['prior_excess_input']),
            new BirFormLine('18B', 'Input Tax on Domestic Purchases', $aggregates['vat_input']),
            new BirFormLine('18D', 'Input Tax on Capital Goods',      $aggregates['vat_input_capital_goods']),

            new BirFormLine('19',  'Total Available Input Tax',
                bcadd(bcadd($aggregates['prior_excess_input'], $aggregates['vat_input'], 2),
                      $aggregates['vat_input_capital_goods'], 2)),

            new BirFormLine('21',  'Total Allowable Input Tax',
                bcadd(bcadd($aggregates['prior_excess_input'], $aggregates['vat_input'], 2),
                      $aggregates['vat_input_capital_goods'], 2)),

            new BirFormLine('22',  'Net VAT Payable / (Excess Input)',
                bcsub($aggregates['output_vat'],
                      bcadd(bcadd($aggregates['prior_excess_input'], $aggregates['vat_input'], 2),
                            $aggregates['vat_input_capital_goods'], 2),
                      2)),

            new BirFormLine('23A', 'VAT Withheld on Sales to Govt',   $aggregates['withheld_vat_on_gov_sales']),
            new BirFormLine('23B', 'Total Tax Credits / Payments',    $taxAvailableCredits),
            new BirFormLine('24',  'Tax Still Due / (Overpayment)',   $netDue),
        ];

        foreach ($lines as $line) {
            $form->addLine($line);
        }

        $form->markGenerated(taxDue: max($netDue, '0.00') === $netDue ? $netDue : '0.00');
        $form->data = [
            'aggregates' => $aggregates,
            'computed' => [
                'tax_due'             => $taxDue,
                'tax_available_credits' => $taxAvailableCredits,
                'net_due'             => $netDue,
            ],
        ];

        return $form;
    }

    /** @param  array<string, string>  $a */
    private function computeTaxDue(array $a): string
    {
        // Output VAT − available input VAT
        $availableInput = bcadd(bcadd($a['prior_excess_input'], $a['vat_input'], 2),
                                 $a['vat_input_capital_goods'], 2);
        return bcsub($a['output_vat'], $availableInput, 2);
    }
}
