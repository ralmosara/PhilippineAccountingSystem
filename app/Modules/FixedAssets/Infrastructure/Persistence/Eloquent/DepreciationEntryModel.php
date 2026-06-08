<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DepreciationEntryModel extends Model
{
    use HasUuids;

    protected $table = 'assets.depreciation_entries';

    /** @var array<int, string> */
    protected $fillable = [
        'fixed_asset_id',
        'period_year',
        'period_month',
        'depreciation_amount',
        'accumulated_after',
        'book_value_after',
        'journal_entry_id',
        'posted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'depreciation_amount' => 'decimal:2',
            'accumulated_after'   => 'decimal:2',
            'book_value_after'    => 'decimal:2',
            'posted_at'           => 'datetime',
        ];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAssetModel::class, 'fixed_asset_id');
    }
}
