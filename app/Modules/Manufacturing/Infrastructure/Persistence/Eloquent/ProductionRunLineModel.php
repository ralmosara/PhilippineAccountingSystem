<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductionRunLineModel extends Model
{
    use HasUuids;

    protected $table = 'manufacturing.production_run_lines';

    /** @var array<int, string> */
    protected $fillable = [
        'work_order_id', 'component_item_id', 'component_name',
        'quantity_required', 'quantity_consumed',
        'unit_cost', 'total_cost',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:4',
            'quantity_consumed' => 'decimal:4',
            'unit_cost'         => 'decimal:2',
            'total_cost'        => 'decimal:2',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrderModel::class, 'work_order_id');
    }
}
