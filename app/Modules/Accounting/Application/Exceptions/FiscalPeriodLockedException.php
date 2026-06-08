<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Exceptions;

use RuntimeException;

final class FiscalPeriodLockedException extends RuntimeException
{
    public function __construct(public readonly string $fiscalPeriodId)
    {
        parent::__construct("Fiscal period {$fiscalPeriodId} is locked; writes are rejected.");
    }
}
