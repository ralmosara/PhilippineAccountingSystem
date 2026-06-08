<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Contracts;

use App\Modules\Projects\Domain\Entities\TimesheetEntry;
use DateTimeImmutable;

interface TimesheetRepositoryContract
{
    public function save(TimesheetEntry $entry): void;

    /** @return list<TimesheetEntry> */
    public function listForProject(string $projectId): array;

    public function sumUnbilledHoursForProject(
        string $projectId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): string;

    /** @return list<TimesheetEntry> */
    public function listUnbilledForProject(
        string $projectId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array;

    public function markBilled(string ...$ids): void;
}
