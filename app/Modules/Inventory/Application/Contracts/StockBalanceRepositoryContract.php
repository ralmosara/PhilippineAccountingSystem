<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Contracts;

use App\Modules\Inventory\Domain\Entities\StockBalance;

interface StockBalanceRepositoryContract
{
    /**
     * Loads the balance and ROW-LOCKS it (SELECT … FOR UPDATE) for the
     * remainder of the transaction. Creates a zero-quantity balance row
     * if one doesn't yet exist for the (item, warehouse) pair.
     *
     * MUST be called inside a transaction.
     */
    public function findOrCreateForUpdate(string $itemId, string $warehouseId): StockBalance;

    public function save(StockBalance $balance): void;
}
