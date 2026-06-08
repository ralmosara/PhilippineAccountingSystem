<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class StockBalanceModel extends Model
{
    use HasUuids;

    protected $table = 'inventory.stock_balances';

    /** @var array<int, string> */
    protected $fillable = ['item_id', 'warehouse_id', 'quantity', 'value', 'last_movement_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'         => 'decimal:4',
            'value'            => 'decimal:2',
            'last_movement_at' => 'datetime',
        ];
    }
}
