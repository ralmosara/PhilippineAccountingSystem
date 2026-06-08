<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BomLineModel extends Model
{
    use HasUuids;

    protected $table = 'manufacturing.bom_lines';

    /** @var array<int, string> */
    protected $fillable = [
        'bom_id', 'component_item_id', 'component_name',
        'quantity_per_batch', 'unit_of_measure', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_per_batch' => 'decimal:4',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BomModel::class, 'bom_id');
    }
}
