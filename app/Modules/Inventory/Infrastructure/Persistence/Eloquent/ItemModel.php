<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ItemModel extends Model
{
    use HasUuids;

    protected $table = 'inventory.items';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'category_id', 'sku', 'name', 'description', 'kind',
        'uom_id', 'costing_method', 'moving_avg_cost', 'standard_cost',
        'selling_price', 'is_vatable', 'is_inventory',
        'track_lots', 'track_serials', 'weight_kg', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_vatable'      => 'boolean',
            'is_inventory'    => 'boolean',
            'track_lots'      => 'boolean',
            'track_serials'   => 'boolean',
            'is_active'       => 'boolean',
            'moving_avg_cost' => 'decimal:4',
            'standard_cost'   => 'decimal:4',
            'selling_price'   => 'decimal:4',
            'weight_kg'       => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategoryModel::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasureModel::class, 'uom_id');
    }
}
