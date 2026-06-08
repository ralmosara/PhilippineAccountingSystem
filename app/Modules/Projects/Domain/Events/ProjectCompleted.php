<?php

declare(strict_types=1);

namespace App\Modules\Projects\Domain\Events;

use DateTimeImmutable;

final readonly class ProjectCompleted
{
    public function __construct(
        public string $projectId,
        public string $companyId,
        public string $code,
        public string $name,
        public DateTimeImmutable $completedAt,
        public string $completedBy,
    ) {
    }
}
