<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\Contracts\StockMovementRepositoryContract;
use App\Modules\Inventory\Domain\Entities\StockMovement;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\StockMovementModel;
use Illuminate\Database\ConnectionInterface;
use Ramsey\Uuid\Uuid;

final readonly class EloquentStockMovementRepository implements StockMovementRepositoryContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function save(StockMovement $movement): void
    {
        StockMovementModel::query()->insert([
            'id'              => $movement->id,
            'item_id'         => $movement->itemId,
            'warehouse_id'    => $movement->warehouseId,
            'movement_type'   => $movement->movementType->value,
            'quantity'        => $movement->quantity,
            'unit_cost'       => $movement->unitCost->amount,
            'total_cost'      => $movement->totalCost->amount,
            'source_doc_id'   => $movement->sourceDocId,
            'source_doc_type' => $movement->sourceDocType,
            'project_id'     => $movement->projectId,
            'moved_at'       => $movement->movedAt,
            'moved_by'       => $movement->movedBy,
            'remarks'        => $movement->remarks,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function snapshotCostingHistory(
        string $itemId,
        string $movementId,
        string $movingAvgCost,
        string $quantityOnHand,
        string $valueOnHand,
    ): void {
        $this->db->insert(
            'INSERT INTO inventory.costing_history '
            .'(id, item_id, effective_at, moving_avg_cost, quantity_on_hand, value_on_hand, trigger_movement_id, created_at, updated_at) '
            .'VALUES (?::uuid, ?::uuid, now(), ?, ?, ?, ?::uuid, now(), now())',
            [Uuid::uuid4()->toString(), $itemId, $movingAvgCost, $quantityOnHand, $valueOnHand, $movementId],
        );
    }
}
