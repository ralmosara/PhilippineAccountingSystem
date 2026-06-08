<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

use InvalidArgumentException;

/**
 * Optional Standard Deduction (OSD) — NIRC §34(L), RA 9504, RR 2-2010, RR 16-2008.
 *
 * Election: an individual or corporate taxpayer may elect, in lieu of
 * itemized deductions, a flat deduction of 40%:
 *
 *   - Individual (1701): 40% of GROSS SALES / GROSS RECEIPTS (not gross income)
 *   - Corporate  (1702): 40% of GROSS INCOME (gross sales − cost of sales/services)
 *
 * Constraints (BIR-enforced, surfaced as exceptions here):
 *   - Election is irrevocable for the entire taxable year; must be declared
 *     on the FIRST quarterly return. Once a 1701-Q/1702-Q is filed with
 *     itemized deductions, OSD is foreclosed for that year.
 *   - Pure compensation earners cannot elect OSD (no gross sales).
 *   - Taxpayers using the 8% flat election (RA 10963) cannot also use OSD;
 *     the 8% rate is its own deduction-equivalent.
 *
 * Returned amount is BCMath-friendly numeric string (2dp).
 */
final readonly class OsdCalculator
{
    /** OSD rate is 40% — fixed by statute since RA 9504 (2008). */
    public const RATE = '0.40';

    /**
     * Individual taxpayer (Form 1701) — 40% × gross sales/receipts.
     *
     * @param  string  $grossSales  pre-discount, pre-return; numeric string
     */
    public function forIndividual(string $grossSales): string
    {
        $this->assertNonNegative($grossSales, 'grossSales');
        return bcmul($grossSales, self::RATE, 2);
    }

    /**
     * Corporate taxpayer (Form 1702-RT) — 40% × gross income, where
     * gross income = gross sales − sales returns/discounts − cost of sales.
     *
     * Note: BIR's "gross income" for OSD purposes is the operating gross
     * margin BEFORE other operating expenses (NOT the IFRS "gross profit",
     * which is the same number under PFRS for SMEs).
     */
    public function forCorporate(string $grossSales, string $salesReturns, string $costOfSales): string
    {
        $this->assertNonNegative($grossSales,   'grossSales');
        $this->assertNonNegative($salesReturns, 'salesReturns');
        $this->assertNonNegative($costOfSales,  'costOfSales');

        $netSales    = bcsub($grossSales, $salesReturns, 2);
        $grossIncome = bcsub($netSales,   $costOfSales,  2);

        // OSD never goes negative — a loss-making cost structure produces 0.
        if (bccomp($grossIncome, '0', 2) <= 0) {
            return '0.00';
        }

        return bcmul($grossIncome, self::RATE, 2);
    }

    /**
     * Guard against electing both OSD and the 8% flat tax simultaneously —
     * RR 8-2018 § 3 forbids this combination because 8% already replaces
     * deductions + percentage tax for the year.
     */
    public function assertNotCombinedWithFlat8Percent(bool $useOsd, bool $electFlat8Percent): void
    {
        if ($useOsd && $electFlat8Percent) {
            throw new InvalidArgumentException(
                'Cannot elect both OSD (40%) and the 8% flat rate on the same return. '
                .'Choose ONE deduction regime per BIR RR 8-2018 § 3.'
            );
        }
    }

    private function assertNonNegative(string $amount, string $field): void
    {
        if (bccomp($amount, '0', 2) < 0) {
            throw new InvalidArgumentException("OSD: {$field} cannot be negative (got {$amount}).");
        }
    }
}
