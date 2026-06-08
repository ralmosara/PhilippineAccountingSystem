<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Services;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Sales\Domain\Entities\Customer;

/**
 * VAT calculator for sales invoice totals.
 *
 * Inputs:  array of line subtotals tagged by tax-code kind (vat_output, vat_zero, vat_exempt)
 * Outputs: vatable_sales, vat_zero_rated_sales, vat_exempt_sales, vat_amount
 *
 * Senior citizen / PWD rule (RA 9994 / RA 10754):
 *   - Customer qualifies → all lines treated as VAT-exempt
 *   - 20% discount applied on the net amount
 *   - VAT amount = 0
 *
 * Government customer rule:
 *   - Standard 12% VAT computed
 *   - 5% final VAT withheld at source (subtracted from total payable)
 */
final readonly class VatCalculator
{
    public const VAT_RATE = '0.12';
    public const SENIOR_PWD_DISCOUNT_RATE = '0.20';
    public const WITHHELD_VAT_GOV_RATE = '0.05';

    /**
     * @param  array<int, array{subtotal: string, kind: 'vat_output'|'vat_zero'|'vat_exempt'}>  $lines
     * @return array{
     *     vatable_sales: Money,
     *     vat_zero_rated_sales: Money,
     *     vat_exempt_sales: Money,
     *     vat_amount: Money,
     *     senior_pwd_discount: Money,
     *     withheld_vat: Money,
     *     total: Money,
     * }
     */
    public function compute(array $lines, Customer $customer): array
    {
        // Senior / PWD: collapse all lines to vat_exempt and apply 20% disc
        if ($customer->qualifiesForSeniorPwdDiscount()) {
            $gross = '0';
            foreach ($lines as $line) {
                $gross = bcadd($gross, $line['subtotal'], 4);
            }
            $discount = bcmul($gross, self::SENIOR_PWD_DISCOUNT_RATE, 4);
            $net = bcsub($gross, $discount, 4);

            return [
                'vatable_sales'        => Money::zero('PHP'),
                'vat_zero_rated_sales' => Money::zero('PHP'),
                'vat_exempt_sales'     => Money::php($net),
                'vat_amount'           => Money::zero('PHP'),
                'senior_pwd_discount'  => Money::php($discount),
                'withheld_vat'         => Money::zero('PHP'),
                'total'                => Money::php($net),
            ];
        }

        $vatable    = '0';
        $zeroRated  = '0';
        $exempt     = '0';

        foreach ($lines as $line) {
            match ($line['kind']) {
                'vat_output' => $vatable   = bcadd($vatable,   $line['subtotal'], 4),
                'vat_zero'   => $zeroRated = bcadd($zeroRated, $line['subtotal'], 4),
                'vat_exempt' => $exempt    = bcadd($exempt,    $line['subtotal'], 4),
            };
        }

        $vatAmount = bcmul($vatable, self::VAT_RATE, 4);
        $total = bcadd(bcadd(bcadd($vatable, $zeroRated, 4), $exempt, 4), $vatAmount, 4);

        $withheldVat = '0';
        if ($customer->isGovernment) {
            $withheldVat = bcmul($vatable, self::WITHHELD_VAT_GOV_RATE, 4);
            $total = bcsub($total, $withheldVat, 4);
        }

        return [
            'vatable_sales'        => Money::php($vatable),
            'vat_zero_rated_sales' => Money::php($zeroRated),
            'vat_exempt_sales'     => Money::php($exempt),
            'vat_amount'           => Money::php($vatAmount),
            'senior_pwd_discount'  => Money::zero('PHP'),
            'withheld_vat'         => Money::php($withheldVat),
            'total'                => Money::php($total),
        ];
    }
}
