<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FixedAssetModel extends Model
{
    use HasUuids;

    protected $table = 'assets.fixed_assets';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id',
        'branch_id',
        'cost_center_id',
        'asset_no',
        'name',
        'description',
        'category',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'accumulated_depreciation',
        'book_value',
        'asset_account_id',
        'accum_depr_account_id',
        'depr_expense_account_id',
        'status',
        'disposed_at',
        'disposal_proceeds',
        'disposal_gain_loss',
        'disposal_journal_entry_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'acquisition_date'         => 'date',
            'acquisition_cost'         => 'decimal:2',
            'salvage_value'            => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'book_value'               => 'decimal:2',
            'disposal_proceeds'        => 'decimal:2',
            'disposal_gain_loss'       => 'decimal:2',
            'disposed_at'              => 'datetime',
        ];
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntryModel::class, 'fixed_asset_id')
                    ->orderBy('period_year')
                    ->orderBy('period_month');
    }
}
