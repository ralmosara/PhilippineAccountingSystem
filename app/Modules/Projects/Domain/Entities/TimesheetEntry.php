<?php

declare(strict_types=1);

namespace App\Modules\Projects\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use DateTimeImmutable;

final readonly class TimesheetEntry
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $employeeId,
        public DateTimeImmutable $workDate,
        public string $hours,
        public Money $billableRate,
        public Money $billableAmount,
        public ?string $description,
        public bool $isBilled,
    ) {
    }
}
