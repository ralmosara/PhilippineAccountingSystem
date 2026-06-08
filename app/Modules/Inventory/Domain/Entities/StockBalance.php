<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * Materialized current balance per (item × warehouse). Updated atomically
 * by the moving-avg calculator on each stock movement.
 */
final class StockBalance
{
    public function __construct(
        public readonly string $itemId,
        public readonly string $warehouseId,
        public string $quantity,             // numeric string for BCMath
        public Money $value,                 // total inventory value at moving-avg cost
        public ?DateTimeImmutable $lastMovementAt = null,
    ) {
    }

    /** Average unit cost = value / quantity (0 if no stock). */
    public function averageUnitCost(): Money
    {
        if (bccomp($this->quantity, '0', 4) <= 0) {
            return Money::zero();
        }
        return Money::php(bcdiv($this->value->amount, $this->quantity, 4));
    }
}
