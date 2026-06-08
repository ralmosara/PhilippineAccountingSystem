<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Events;

use DateTimeImmutable;

final readonly class StockMoved
{
    public function __construct(
        public string $stockMovementId,
        public string $companyId,
        public string $itemId,
        public string $warehouseId,
        public string $movementType,
        public string $quantity,
        public string $unitCost,
        public string $totalCost,
        public ?string $sourceDocId,
        public ?string $sourceDocType,
        public DateTimeImmutable $movedAt,
    ) {
    }
}
