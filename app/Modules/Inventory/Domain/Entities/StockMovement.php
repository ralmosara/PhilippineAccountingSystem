<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final readonly class StockMovement
{
    public function __construct(
        public string $id,
        public string $itemId,
        public string $warehouseId,
        public MovementType $movementType,
        public string $quantity,                  // signed: + inbound / − outbound
        public Money $unitCost,
        public Money $totalCost,
        public ?string $sourceDocId,
        public ?string $sourceDocType,
        public ?string $projectId,
        public DateTimeImmutable $movedAt,
        public ?string $movedBy,
        public ?string $remarks,
    ) {
    }

    public static function generateId(): string
    {
        return Uuid::uuid4()->toString();
    }
}
