<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BomModel extends Model
{
    use HasUuids;

    protected $table = 'manufacturing.bills_of_materials';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'item_id', 'item_name', 'code', 'name',
        'version', 'status', 'standard_batch_size',
        'labor_cost_per_batch', 'overhead_cost_per_batch', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'standard_batch_size'    => 'decimal:4',
            'labor_cost_per_batch'   => 'decimal:2',
            'overhead_cost_per_batch' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BomLineModel::class, 'bom_id');
    }
}
