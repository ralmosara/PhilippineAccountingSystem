<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Domain\Events;

final readonly class ProductionRunPosted
{
    public function __construct(
        public string $workOrderId,
        public string $workOrderNo,
        public string $companyId,
        public string $journalEntryId,
        public string $totalProductionCost,
        public int    $linesConsumed,
        public string $postedAt,   // ISO-8601
    ) {
    }
}
