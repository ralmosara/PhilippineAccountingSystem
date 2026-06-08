<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Services;

use App\Modules\Accounting\Domain\ValueObjects\Money;

/**
 * Computes the creditable / final withholding tax for a vendor bill.
 *
 * Inputs:  ATC code (e.g. 'WC100' = 2%, 'WI011' = 10%) + income-payment base
 * Outputs: tax_withheld amount + the rate used
 *
 * The base is the **gross of VAT** for goods and **net of VAT** for services
 * per RR 11-2018 — but this is a controversial point and many CPAs apply
 * "net of VAT" universally for simplicity. We default to net-of-VAT, with
 * a `$baseIncludesVat` toggle for cases where the regulation requires the
 * gross base (rentals being the main example).
 *
 * Phase 1: rate is read from the seeded tax.atc_codes table by the
 * Application layer and passed in here as a string. We don't call the
 * DB from a domain service.
 */
final readonly class WithholdingTaxCalculator
{
    /**
     * @return array{
     *     tax_withheld: Money,
     *     rate: string,
     *     base: Money,
     * }
     */
    public function compute(Money $base, string $atcCode, string $rate): array
    {
        // rate is decimal string, e.g. '0.0500' = 5%
        $withheld = bcmul($base->amount, $rate, 4);

        return [
            'tax_withheld' => Money::php($withheld),
            'rate'         => $rate,
            'base'         => $base,
        ];
    }

    /**
     * Helper: derive the WT base from invoice subtotal + VAT, given the rule.
     * @param  bool  $includeVat  true → gross of VAT (rentals); false → net of VAT (default)
     */
    public function baseFor(Money $subtotal, Money $vatAmount, bool $includeVat = false): Money
    {
        return $includeVat ? $subtotal->add($vatAmount) : $subtotal;
    }
}
