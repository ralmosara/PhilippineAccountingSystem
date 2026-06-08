<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockMovementModel extends Model
{
    use HasUuids;

    protected $table = 'inventory.stock_movements';

    /** @var array<int, string> */
    protected $fillable = [
        'item_id', 'warehouse_id', 'movement_type',
        'quantity', 'unit_cost', 'total_cost',
        'source_doc_id', 'source_doc_type', 'project_id',
        'moved_at', 'moved_by', 'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_cost'  => 'decimal:4',
            'total_cost' => 'decimal:2',
            'moved_at'   => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }
}
