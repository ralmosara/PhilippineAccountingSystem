<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Contracts;

use App\Modules\Inventory\Domain\Entities\StockMovement;

interface StockMovementRepositoryContract
{
    public function save(StockMovement $movement): void;

    public function snapshotCostingHistory(
        string $itemId,
        string $movementId,
        string $movingAvgCost,
        string $quantityOnHand,
        string $valueOnHand,
    ): void;
}
