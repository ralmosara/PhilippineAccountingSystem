<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Application\Contracts\StockBalanceRepositoryContract;
use App\Modules\Inventory\Domain\Entities\StockBalance;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Ramsey\Uuid\Uuid;

final readonly class EloquentStockBalanceRepository implements StockBalanceRepositoryContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function findOrCreateForUpdate(string $itemId, string $warehouseId): StockBalance
    {
        // Lock the row if it exists
        $row = $this->db->selectOne(
            'SELECT quantity, value, last_movement_at FROM inventory.stock_balances '
            .'WHERE item_id = ?::uuid AND warehouse_id = ?::uuid FOR UPDATE',
            [$itemId, $warehouseId],
        );

        if ($row !== null) {
            return new StockBalance(
                itemId:         $itemId,
                warehouseId:    $warehouseId,
                quantity:       (string) $row->quantity,
                value:          Money::php((string) $row->value),
                lastMovementAt: $row->last_movement_at
                    ? new DateTimeImmutable($row->last_movement_at)
                    : null,
            );
        }

        // Create a zero balance row and re-fetch with lock
        $this->db->insert(
            'INSERT INTO inventory.stock_balances (id, item_id, warehouse_id, quantity, value, created_at, updated_at) '
            .'VALUES (?::uuid, ?::uuid, ?::uuid, 0, 0, now(), now()) '
            .'ON CONFLICT (item_id, warehouse_id) DO NOTHING',
            [Uuid::uuid4()->toString(), $itemId, $warehouseId],
        );

        return new StockBalance(
            itemId:      $itemId,
            warehouseId: $warehouseId,
            quantity:    '0.0000',
            value:       Money::zero(),
        );
    }

    public function save(StockBalance $balance): void
    {
        $this->db->update(
            'UPDATE inventory.stock_balances SET quantity = ?, value = ?, last_movement_at = ?, updated_at = now() '
            .'WHERE item_id = ?::uuid AND warehouse_id = ?::uuid',
            [
                $balance->quantity,
                $balance->value->toPhp(),
                $balance->lastMovementAt?->format('Y-m-d H:i:s.uP'),
                $balance->itemId,
                $balance->warehouseId,
            ],
        );
    }
}
