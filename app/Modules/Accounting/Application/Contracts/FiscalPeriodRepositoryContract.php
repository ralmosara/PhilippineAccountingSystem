<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Contracts;

use DateTimeImmutable;

interface FiscalPeriodRepositoryContract
{
    /**
     * Finds the fiscal_period_id that contains the given date, for a company.
     * Returns null if the date falls outside any defined fiscal year.
     */
    public function findContaining(string $companyId, DateTimeImmutable $date): ?string;

    public function isLocked(string $fiscalPeriodId): bool;

    public function lock(string $fiscalPeriodId, string $lockedBy, ?string $reason = null): void;
}
