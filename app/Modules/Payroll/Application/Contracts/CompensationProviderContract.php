<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Contracts;

use DateTimeImmutable;

interface CompensationProviderContract
{
    /**
     * Returns the active compensation snapshot for an employee on a given date.
     *
     * @return array{
     *     basic_monthly: string,
     *     taxable_allowances: string,
     *     nontaxable_allowances: string,
     *     is_minimum_wage_earner: bool,
     * }|null
     */
    public function findActive(string $employeeId, DateTimeImmutable $asOf): ?array;
}
