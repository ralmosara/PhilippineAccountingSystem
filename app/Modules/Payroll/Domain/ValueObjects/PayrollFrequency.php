<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\ValueObjects;

enum PayrollFrequency: string
{
    case Monthly      = 'monthly';
    case SemiMonthly  = 'semimonthly';
    case BiWeekly     = 'biweekly';
    case Weekly       = 'weekly';

    /** Periods per year — used to annualize/de-annualize tax brackets. */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly     => 12,
            self::SemiMonthly => 24,
            self::BiWeekly    => 26,
            self::Weekly      => 52,
        };
    }
}
