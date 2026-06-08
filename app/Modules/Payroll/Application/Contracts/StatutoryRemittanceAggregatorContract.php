<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Contracts;

use App\Modules\Payroll\Domain\Entities\StatutoryRemittanceLine;
use DateTimeImmutable;

interface StatutoryRemittanceAggregatorContract
{
    /**
     * Reads APPROVED payroll runs whose period overlaps [from, to], sums
     * per-employee contributions (EE + ER + EC where applicable), and
     * returns one StatutoryRemittanceLine per employee.
     *
     * @return list<StatutoryRemittanceLine>
     */
    public function aggregateForPeriod(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array;
}
