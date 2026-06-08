<?php

declare(strict_types=1);

namespace App\Modules\Projects\Domain\Events;

use DateTimeImmutable;

final readonly class WipRecognized
{
    public function __construct(
        public string $wipEntryId,
        public string $projectId,
        public string $companyId,
        public string $periodFrom,
        public string $periodTo,
        public string $totalHours,
        public string $recognizedRevenue,
        public ?string $journalEntryId,
        public DateTimeImmutable $postedAt,
        public string $postedBy,
    ) {
    }
}
