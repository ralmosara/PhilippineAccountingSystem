<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

/**
 * Corporate Income Tax computation under CREATE Act (RA 11534, effective
 * July 1, 2020).
 *
 * Rates:
 *   - **MSME rate (20%)**: net taxable income ≤ ₱5,000,000 AND total assets
 *     (excluding land) ≤ ₱100,000,000 — both conditions must hold.
 *   - **Regular rate (25%)**: everyone else.
 *
 * Minimum Corporate Income Tax (MCIT):
 *   - 1% of gross income (Jul 2020 – Jun 2023, per CREATE temporary relief)
 *   - 2% of gross income thereafter
 *   - Applied when MCIT > Regular Tax (corporations on their 4th taxable
 *     year onwards). Phase 1 returns the comparison so the caller can
 *     pick the higher value.
 *
 * Improperly Accumulated Earnings Tax (IAET) is out of scope here — that's
 * a separate computation in CREATE-era for closely-held corps.
 */
final readonly class CorporateIncomeTaxCalculator
{
    public const RATE_REGULAR = '0.25';
    public const RATE_MSME    = '0.20';
    public const RATE_MCIT    = '0.02';                   // 2% post-Jun 2023

    public const MSME_INCOME_CEILING = '5000000.00';
    public const MSME_ASSETS_CEILING = '100000000.00';

    /**
     * @return array{
     *     applied_rate: string,
     *     applied_rate_pct: string,
     *     is_msme: bool,
     *     regular_tax: string,
     *     mcit_amount: string,
     *     tax_due_higher: string,
     * }
     */
    public function compute(
        string $taxableIncome,
        string $grossIncome,
        string $totalAssetsExcludingLand,
    ): array {
        $isMsme = $this->qualifiesAsMsme($taxableIncome, $totalAssetsExcludingLand);
        $rate   = $isMsme ? self::RATE_MSME : self::RATE_REGULAR;

        // Regular tax = taxable_income × rate (0 if taxable_income is negative)
        $regularTax = bccomp($taxableIncome, '0', 2) > 0
            ? bcmul($taxableIncome, $rate, 2)
            : '0.00';

        // MCIT = gross_income × 2% (only meaningful for corps on 4th year+)
        $mcit = bccomp($grossIncome, '0', 2) > 0
            ? bcmul($grossIncome, self::RATE_MCIT, 2)
            : '0.00';

        // Higher of the two (per NIRC §27(E))
        $higher = bccomp($regularTax, $mcit, 2) >= 0 ? $regularTax : $mcit;

        return [
            'applied_rate'      => $rate,
            'applied_rate_pct'  => bcmul($rate, '100', 2).'%',
            'is_msme'           => $isMsme,
            'regular_tax'       => $regularTax,
            'mcit_amount'       => $mcit,
            'tax_due_higher'    => $higher,
        ];
    }

    private function qualifiesAsMsme(string $taxableIncome, string $totalAssets): bool
    {
        return bccomp($taxableIncome, self::MSME_INCOME_CEILING, 2) <= 0
            && bccomp($totalAssets,   self::MSME_ASSETS_CEILING, 2) <= 0;
    }
}
