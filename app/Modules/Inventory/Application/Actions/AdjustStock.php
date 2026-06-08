<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Domain\Entities\StockMovement;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use DateTimeImmutable;

/**
 * Manual stock adjustment (positive or negative).
 *
 * Thin wrapper around RecordStockMovement that requires a reason and
 * carries the MovementType::Adjustment marker so reports can distinguish
 * physical-count corrections from operational receipts/issues.
 *
 * Per BIR rules, adjustments must be authorized — the route uses MFA
 * middleware to enforce Approver-role + MFA on the caller.
 */
final readonly class AdjustStock
{
    public function __construct(
        private RecordStockMovement $recordMovement,
    ) {
    }

    public function execute(
        string $itemId,
        string $warehouseId,
        string $quantity,                  // unsigned magnitude
        bool $isPositive,                  // true = inbound adjustment; false = outbound
        Money $unitCost,
        string $reason,
        string $actorId,
        ?DateTimeImmutable $movedAt = null,
    ): StockMovement {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Adjustment reason is required.');
        }

        return $this->recordMovement->execute(
            itemId:        $itemId,
            warehouseId:   $warehouseId,
            movementType:  $isPositive
                ? MovementType::Adjustment
                : MovementType::Issue,                   // outbound adjustment recorded as issue
            quantity:      $quantity,
            unitCost:      $unitCost,
            movedAt:       $movedAt,
            sourceDocId:   null,
            sourceDocType: 'StockAdjustment',
            projectId:     null,
            remarks:       "Adjustment: {$reason}",
            actorId:       $actorId,
        );
    }
}
