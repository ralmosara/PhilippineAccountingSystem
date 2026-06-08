<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Inventory\Application\Contracts\ItemRepositoryContract;
use App\Modules\Inventory\Application\Contracts\StockBalanceRepositoryContract;
use App\Modules\Inventory\Application\Contracts\StockMovementRepositoryContract;
use App\Modules\Inventory\Application\Exceptions\ItemNotFoundException;
use App\Modules\Inventory\Domain\Entities\StockMovement;
use App\Modules\Inventory\Domain\Events\StockMoved;
use App\Modules\Inventory\Domain\Exceptions\ItemNotInventoriedException;
use App\Modules\Inventory\Domain\Services\MovingAverageCalculator;
use App\Modules\Inventory\Domain\ValueObjects\ItemId;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Records a stock movement and updates the moving-avg cost atomically.
 *
 * Concurrency: the StockBalanceRepository.findOrCreateForUpdate() uses
 * SELECT … FOR UPDATE to row-lock the balance for the duration of the
 * transaction. Concurrent movements on the same (item, warehouse) wait
 * for the lock — guaranteeing consistent moving-avg computation.
 */
final readonly class RecordStockMovement
{
    public function __construct(
        private ItemRepositoryContract $items,
        private StockBalanceRepositoryContract $balances,
        private StockMovementRepositoryContract $movements,
        private MovingAverageCalculator $maCalculator,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(
        string $itemId,
        string $warehouseId,
        MovementType $movementType,
        string $quantity,                          // unsigned magnitude
        ?Money $unitCost,                          // ignored for outbound
        ?DateTimeImmutable $movedAt,
        ?string $sourceDocId,
        ?string $sourceDocType,
        ?string $projectId,
        ?string $remarks,
        string $actorId,
    ): StockMovement {
        $item = $this->items->findById(new ItemId($itemId))
            ?? throw new ItemNotFoundException($itemId);

        if (! $item->isInventory) {
            throw new ItemNotInventoriedException($itemId);
        }

        $movedAt ??= new DateTimeImmutable();
        $unitCost ??= $item->movingAvgCost;

        return DB::transaction(function () use (
            $item, $warehouseId, $movementType, $quantity, $unitCost,
            $movedAt, $sourceDocId, $sourceDocType, $projectId, $remarks, $actorId
        ) {
            // 1. Lock the balance row
            $current = $this->balances->findOrCreateForUpdate($item->id->value, $warehouseId);

            // 2. Apply via Moving Average Calculator
            $result = $this->maCalculator->apply($current, $movementType, $quantity, $unitCost, $movedAt);

            // 3. Persist new balance
            $this->balances->save($result['new_balance']);

            // 4. Persist the movement (signed quantity)
            $signedQty = $movementType->isInbound()
                ? $quantity
                : bcmul($quantity, '-1', 4);

            $movement = new StockMovement(
                id:             StockMovement::generateId(),
                itemId:         $item->id->value,
                warehouseId:    $warehouseId,
                movementType:   $movementType,
                quantity:       $signedQty,
                unitCost:       $result['effective_unit_cost'],
                totalCost:      $result['effective_total_cost'],
                sourceDocId:    $sourceDocId,
                sourceDocType:  $sourceDocType,
                projectId:      $projectId,
                movedAt:        $movedAt,
                movedBy:        $actorId,
                remarks:        $remarks,
            );
            $this->movements->save($movement);

            // 5. Snapshot costing history + update item.moving_avg_cost (for inbound)
            if ($movementType->affectsMovingAverage()) {
                $newAvg = $result['new_balance']->averageUnitCost();
                $this->items->updateMovingAvgCost($item->id, $newAvg->amount);

                $this->movements->snapshotCostingHistory(
                    itemId:         $item->id->value,
                    movementId:     $movement->id,
                    movingAvgCost:  $newAvg->amount,
                    quantityOnHand: $result['new_balance']->quantity,
                    valueOnHand:    $result['new_balance']->value->amount,
                );
            }

            // 6. Audit + event
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $item->companyId,
                eventType:   'stock.moved',
                aggregate:   'StockMovement',
                aggregateId: $movement->id,
                payload: [
                    'item_id'         => $item->id->value,
                    'warehouse_id'    => $warehouseId,
                    'movement_type'   => $movementType->value,
                    'quantity'        => $signedQty,
                    'unit_cost'       => $result['effective_unit_cost']->toPhp(),
                    'total_cost'      => $result['effective_total_cost']->toPhp(),
                    'source_doc_id'   => $sourceDocId,
                    'source_doc_type' => $sourceDocType,
                ],
            );

            $this->events->dispatch(new StockMoved(
                stockMovementId: $movement->id,
                companyId:       $item->companyId,
                itemId:          $item->id->value,
                warehouseId:     $warehouseId,
                movementType:    $movementType->value,
                quantity:        $signedQty,
                unitCost:        $result['effective_unit_cost']->toPhp(),
                totalCost:       $result['effective_total_cost']->toPhp(),
                sourceDocId:     $sourceDocId,
                sourceDocType:   $sourceDocType,
                movedAt:         $movedAt,
            ));

            return $movement;
        });
    }
}
