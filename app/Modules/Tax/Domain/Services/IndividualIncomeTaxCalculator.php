<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

/**
 * Individual Income Tax computation under TRAIN Law (RA 10963).
 *
 * Two paths:
 *   - **Graduated rates** (default): 0% / 15% / 20% / 25% / 30% / 35% brackets
 *     applied to annual taxable income.
 *   - **8% flat tax** (election): for purely self-employed / professionals
 *     whose gross sales/receipts ≤ ₱3,000,000 VAT threshold. Flat 8% on
 *     gross sales in lieu of the graduated tax + 3% percentage tax.
 *     Election is per-year and made on the 1st quarter ITR.
 *
 * Mixed-income earners (compensation + business) can only use 8% on the
 * self-employment portion; compensation always uses graduated.
 */
final readonly class IndividualIncomeTaxCalculator
{
    public const FLAT_RATE = '0.08';
    public const FLAT_RATE_VAT_THRESHOLD = '3000000.00';

    /** TRAIN Law annual brackets (post-RA 10963). */
    private const ANNUAL_BRACKETS = [
        ['floor' =>       '0.00', 'ceiling' =>  '250000.00', 'base_tax' =>      '0.00', 'rate' => '0.00'],
        ['floor' =>  '250000.01', 'ceiling' =>  '400000.00', 'base_tax' =>      '0.00', 'rate' => '0.15'],
        ['floor' =>  '400000.01', 'ceiling' =>  '800000.00', 'base_tax' =>  '22500.00', 'rate' => '0.20'],
        ['floor' =>  '800000.01', 'ceiling' => '2000000.00', 'base_tax' => '102500.00', 'rate' => '0.25'],
        ['floor' => '2000000.01', 'ceiling' => '8000000.00', 'base_tax' => '402500.00', 'rate' => '0.30'],
        ['floor' => '8000000.01', 'ceiling' => null,         'base_tax' =>'2202500.00', 'rate' => '0.35'],
    ];

    /**
     * @return array{
     *     method: 'graduated'|'flat_8pct',
     *     tax_due: string,
     *     applied_rate_pct: string|null,
     * }
     */
    public function compute(
        string $taxableIncome,
        string $grossSales,
        bool $electFlat8Percent = false,
    ): array {
        // 8% flat tax — only if elected AND eligible (gross sales ≤ ₱3M VAT threshold)
        if ($electFlat8Percent && $this->isFlatEligible($grossSales)) {
            return [
                'method'           => 'flat_8pct',
                'tax_due'          => $this->compute8PercentFlat($grossSales),
                'applied_rate_pct' => '8%',
            ];
        }

        // Graduated rates path
        return [
            'method'           => 'graduated',
            'tax_due'          => $this->computeGraduated($taxableIncome),
            'applied_rate_pct' => null,                       // varies per bracket
        ];
    }

    public function isFlatEligible(string $grossSales): bool
    {
        return bccomp($grossSales, self::FLAT_RATE_VAT_THRESHOLD, 2) <= 0;
    }

    /**
     * 8% flat tax: gross sales − ₱250,000 deduction (first-time election only) × 8%.
     * For Phase 1 we apply 8% on gross sales directly; the ₱250k deduction
     * applies only for purely self-employed first-time electors and varies
     * by case — left as caller's responsibility.
     */
    private function compute8PercentFlat(string $grossSales): string
    {
        if (bccomp($grossSales, '0', 2) <= 0) {
            return '0.00';
        }
        return bcmul($grossSales, self::FLAT_RATE, 2);
    }

    /**
     * Graduated tax = base_tax + (taxable_income − floor) × rate
     * for the matching bracket.
     */
    private function computeGraduated(string $taxableIncome): string
    {
        if (bccomp($taxableIncome, '0', 2) <= 0) {
            return '0.00';
        }

        foreach (self::ANNUAL_BRACKETS as $b) {
            $aboveFloor   = bccomp($taxableIncome, $b['floor'], 2) >= 0;
            $belowCeiling = $b['ceiling'] === null
                || bccomp($taxableIncome, $b['ceiling'], 2) <= 0;

            if ($aboveFloor && $belowCeiling) {
                $excess      = bcsub($taxableIncome, $b['floor'], 2);
                $taxOnExcess = bcmul($excess, $b['rate'], 2);
                return bcadd($b['base_tax'], $taxOnExcess, 2);
            }
        }

        // Above top bracket — use last bracket's formula
        $top    = end(self::ANNUAL_BRACKETS);
        $excess = bcsub($taxableIncome, $top['floor'], 2);
        return bcadd($top['base_tax'], bcmul($excess, $top['rate'], 2), 2);
    }
}
