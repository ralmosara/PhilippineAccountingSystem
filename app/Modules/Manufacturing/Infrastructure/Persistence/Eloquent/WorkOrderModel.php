<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkOrderModel extends Model
{
    use HasUuids;

    protected $table = 'manufacturing.work_orders';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'bom_id', 'work_order_no',
        'quantity_to_produce', 'quantity_produced', 'status',
        'scheduled_start', 'scheduled_end',
        'actual_start', 'actual_end',
        'warehouse_id', 'wip_account_id',
        'finished_goods_account_id', 'raw_materials_account_id',
        'total_material_cost', 'total_labor_cost',
        'total_overhead_cost', 'total_production_cost',
        'journal_entry_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_to_produce'   => 'decimal:4',
            'quantity_produced'     => 'decimal:4',
            'total_material_cost'   => 'decimal:2',
            'total_labor_cost'      => 'decimal:2',
            'total_overhead_cost'   => 'decimal:2',
            'total_production_cost' => 'decimal:2',
            'scheduled_start'       => 'date',
            'scheduled_end'         => 'date',
            'actual_start'          => 'datetime',
            'actual_end'            => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionRunLineModel::class, 'work_order_id');
    }
}
