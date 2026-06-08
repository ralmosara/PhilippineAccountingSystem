<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence;

use App\Modules\Accounting\Application\Contracts\FiscalPeriodRepositoryContract;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\FiscalPeriodModel;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\FiscalYearModel;
use DateTimeImmutable;

final class EloquentFiscalPeriodRepository implements FiscalPeriodRepositoryContract
{
    public function findContaining(string $companyId, DateTimeImmutable $date): ?string
    {
        $row = FiscalPeriodModel::query()
            ->select('accounting.fiscal_periods.id')
            ->join('accounting.fiscal_years', 'accounting.fiscal_periods.fiscal_year_id', '=', 'accounting.fiscal_years.id')
            ->where('accounting.fiscal_years.company_id', $companyId)
            ->where('accounting.fiscal_periods.starts_on', '<=', $date->format('Y-m-d'))
            ->where('accounting.fiscal_periods.ends_on',   '>=', $date->format('Y-m-d'))
            ->first();

        return $row?->id;
    }

    public function isLocked(string $fiscalPeriodId): bool
    {
        return FiscalPeriodModel::query()
            ->where('id', $fiscalPeriodId)
            ->whereNotNull('locked_at')
            ->exists();
    }

    public function lock(string $fiscalPeriodId, string $lockedBy, ?string $reason = null): void
    {
        FiscalPeriodModel::query()
            ->where('id', $fiscalPeriodId)
            ->whereNull('locked_at')
            ->update([
                'locked_at'   => now(),
                'locked_by'   => $lockedBy,
                'lock_reason' => $reason,
            ]);
    }
}
