<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Domain\Events;

final readonly class WorkOrderCompleted
{
    public function __construct(
        public string $workOrderId,
        public string $workOrderNo,
        public string $companyId,
        public string $quantityProduced,
        public string $totalProductionCost,
        public ?string $journalEntryId,
        public string $completedAt,   // ISO-8601
        public string $actorId,
    ) {
    }
}
