<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Contracts;

use App\Modules\Payroll\Domain\ValueObjects\PayrollFrequency;
use DateTimeImmutable;

interface StatutoryRateProviderContract
{
    /**
     * Returns all rate tables effective on the given date.
     *
     * @return array{
     *     sss_brackets: list<array{msc_floor: string, msc_ceiling: string, ee_amount: string, er_amount: string}>,
     *     philhealth: array{premium_rate: string, salary_floor: string, salary_ceiling: string},
     *     pagibig: array{ee_rate_low: string, ee_rate_high: string, er_rate: string, low_threshold: string, salary_cap: string},
     *     bir_brackets: list<array{floor: string, ceiling: string|null, base_tax: string, rate: string}>,
     * }
     */
    public function ratesEffectiveOn(DateTimeImmutable $date, PayrollFrequency $frequency): array;
}
