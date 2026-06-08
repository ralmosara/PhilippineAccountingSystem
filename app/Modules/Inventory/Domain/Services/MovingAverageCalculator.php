<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Domain\Entities\StockBalance;
use App\Modules\Inventory\Domain\Exceptions\NegativeStockException;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Moving-average cost calculator — pure domain logic.
 *
 * The Application layer owns the row-locking (SELECT … FOR UPDATE on
 * stock_balances) and the persistence of the new balance + costing
 * history snapshot. This class only computes the new state.
 *
 * Receipt formula:
 *   new_quantity = current_qty + receipt_qty
 *   new_value    = current_value + (receipt_qty × receipt_unit_cost)
 *   new_ma_cost  = new_value / new_quantity      (rounded 4 dp)
 *
 * Issue formula:
 *   cost_at_issue = current_ma_cost
 *   new_quantity  = current_qty − issue_qty
 *   new_value     = current_value − (issue_qty × current_ma_cost)
 *   ma_cost       = unchanged
 */
final readonly class MovingAverageCalculator
{
    /**
     * Apply a movement to a balance and return the new balance + the
     * effective unit cost used for the movement.
     *
     * @return array{
     *     new_balance: StockBalance,
     *     effective_unit_cost: Money,
     *     effective_total_cost: Money,
     * }
     */
    public function apply(
        StockBalance $current,
        MovementType $type,
        string $quantity,                         // unsigned magnitude
        Money $unitCost,                          // for inbound: actual cost; for outbound: ignored (uses MA)
        DateTimeImmutable $at,
    ): array {
        if (bccomp($quantity, '0', 4) <= 0) {
            throw new InvalidArgumentException("Quantity must be > 0; got {$quantity}");
        }

        return $type->isInbound()
            ? $this->applyInbound($current, $quantity, $unitCost, $at)
            : $this->applyOutbound($current, $quantity, $at);
    }

    /** @return array{new_balance: StockBalance, effective_unit_cost: Money, effective_total_cost: Money} */
    private function applyInbound(StockBalance $current, string $qty, Money $cost, DateTimeImmutable $at): array
    {
        $newQty   = bcadd($current->quantity, $qty, 4);
        $addValue = bcmul($qty, $cost->amount, 4);
        $newValue = bcadd($current->value->amount, $addValue, 2);

        return [
            'new_balance'          => new StockBalance(
                itemId:           $current->itemId,
                warehouseId:      $current->warehouseId,
                quantity:         $newQty,
                value:            Money::php($newValue),
                lastMovementAt:   $at,
            ),
            'effective_unit_cost'  => $cost,
            'effective_total_cost' => Money::php($addValue),
        ];
    }

    /** @return array{new_balance: StockBalance, effective_unit_cost: Money, effective_total_cost: Money} */
    private function applyOutbound(StockBalance $current, string $qty, DateTimeImmutable $at): array
    {
        $newQty = bcsub($current->quantity, $qty, 4);
        if (bccomp($newQty, '0', 4) < 0) {
            throw new NegativeStockException($current->itemId, $current->warehouseId, $current->quantity, $qty);
        }

        $maCost   = $current->averageUnitCost();
        $issueVal = bcmul($qty, $maCost->amount, 4);
        $newValue = bcsub($current->value->amount, $issueVal, 2);

        // Floating-point cleanup: if quantity went to 0, force value to 0
        if (bccomp($newQty, '0', 4) === 0) {
            $newValue = '0.00';
        }

        return [
            'new_balance'          => new StockBalance(
                itemId:           $current->itemId,
                warehouseId:      $current->warehouseId,
                quantity:         $newQty,
                value:            Money::php($newValue),
                lastMovementAt:   $at,
            ),
            'effective_unit_cost'  => $maCost,
            'effective_total_cost' => Money::php($issueVal),
        ];
    }
}
