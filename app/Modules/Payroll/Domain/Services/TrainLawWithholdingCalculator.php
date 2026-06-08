<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Services;

use App\Modules\Accounting\Domain\ValueObjects\Money;

/**
 * TRAIN Law (RA 10963) withholding tax computation on compensation.
 *
 * Brackets are loaded from `payroll.bir_tax_tables` per frequency:
 *   - daily:        ≤685 → 0; >685 → bracket
 *   - weekly:       ≤4,808 → 0
 *   - semimonthly:  ≤10,417 → 0  (this is the most common payroll frequency)
 *   - monthly:      ≤20,833 → 0
 *   - annual:       ≤250,000 → 0
 *
 * The bracket format: [{floor, ceiling, base_tax, rate}, ...]
 *   tax = base_tax + (taxable_compensation - floor) × rate
 */
final readonly class TrainLawWithholdingCalculator
{
    /**
     * Minimum Wage Earners (MWE) are completely exempt from WT on basic +
     * statutory benefits — RA 9504. The caller checks the MWE flag on the
     * compensation_package and skips this method entirely if applicable.
     *
     * @param  list<array{floor: string, ceiling: string|null, base_tax: string, rate: string}>  $brackets
     */
    public function compute(Money $taxableCompensation, array $brackets): Money
    {
        $amount = $taxableCompensation->amount;

        // ≤ first bracket floor → 0 tax
        if (bccomp($amount, $brackets[0]['floor'], 2) < 0) {
            return Money::zero();
        }

        foreach ($brackets as $b) {
            $aboveFloor = bccomp($amount, $b['floor'], 2) >= 0;
            $belowCeiling = $b['ceiling'] === null || bccomp($amount, $b['ceiling'], 2) <= 0;

            if ($aboveFloor && $belowCeiling) {
                // tax = base_tax + (amount - floor) × rate
                $excess = bcsub($amount, $b['floor'], 2);
                $taxOnExcess = bcmul($excess, $b['rate'], 2);
                return Money::php(bcadd($b['base_tax'], $taxOnExcess, 2));
            }
        }

        // Above the top bracket — use the last bracket's formula
        $top = end($brackets);
        $excess = bcsub($amount, $top['floor'], 2);
        return Money::php(bcadd($top['base_tax'], bcmul($excess, $top['rate'], 2), 2));
    }
}
