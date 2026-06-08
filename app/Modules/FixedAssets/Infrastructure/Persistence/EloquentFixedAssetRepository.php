<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Infrastructure\Persistence;

use App\Modules\FixedAssets\Application\Contracts\FixedAssetRepositoryContract;
use App\Modules\FixedAssets\Domain\Entities\FixedAsset;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetCategory;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetId;
use App\Modules\FixedAssets\Domain\ValueObjects\DepreciationMethod;
use App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent\FixedAssetModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentFixedAssetRepository implements FixedAssetRepositoryContract
{
    public function findById(AssetId $id): ?FixedAsset
    {
        $model = FixedAssetModel::query()->find($id->value);

        return $model ? $this->hydrate($model) : null;
    }

    public function save(FixedAsset $asset): void
    {
        FixedAssetModel::query()->updateOrCreate(
            ['id' => $asset->id->value],
            [
                'company_id'               => $asset->companyId,
                'branch_id'                => $asset->branchId,
                'cost_center_id'           => $asset->costCenterId,
                'asset_no'                 => $asset->assetNo,
                'name'                     => $asset->name,
                'description'              => $asset->description,
                'category'                 => $asset->category->value,
                'acquisition_date'         => $asset->acquisitionDate->format('Y-m-d'),
                'acquisition_cost'         => $asset->acquisitionCost,
                'salvage_value'            => $asset->salvageValue,
                'useful_life_months'       => $asset->usefulLifeMonths,
                'depreciation_method'      => $asset->depreciationMethod->value,
                'accumulated_depreciation' => $asset->accumulatedDepreciation,
                'book_value'               => $asset->bookValue,
                'asset_account_id'         => $asset->assetAccountId,
                'accum_depr_account_id'    => $asset->accumDeprAccountId,
                'depr_expense_account_id'  => $asset->deprExpenseAccountId,
                'status'                   => $asset->status,
                'disposed_at'              => $asset->disposedAt?->format('Y-m-d H:i:sP'),
                'disposal_proceeds'        => $asset->disposalProceeds,
                'disposal_gain_loss'       => $asset->disposalGainLoss,
                'disposal_journal_entry_id'=> $asset->disposalJournalEntryId,
            ]
        );
    }

    /** @return list<FixedAsset> */
    public function listForCompany(string $companyId): array
    {
        return FixedAssetModel::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => $this->hydrate($m))
            ->all();
    }

    /** @return list<FixedAsset> */
    public function listActiveForCompany(string $companyId): array
    {
        return FixedAssetModel::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('useful_life_months', '>', 0)
            ->get()
            ->map(fn ($m) => $this->hydrate($m))
            ->all();
    }

    /**
     * Generate and reserve the next asset number using a database count.
     * Format: FA-{YYYY}-{NNN}
     */
    public function nextAssetNo(string $companyId): string
    {
        // Lock the company's asset rows to prevent concurrent numbering conflicts
        $count = DB::table('assets.fixed_assets')
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->count();

        $year   = (int) date('Y');
        $serial = str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);

        return "FA-{$year}-{$serial}";
    }

    private function hydrate(FixedAssetModel $model): FixedAsset
    {
        $asset = new FixedAsset(
            id:                      new AssetId($model->id),
            companyId:               $model->company_id,
            assetNo:                 $model->asset_no,
            name:                    $model->name,
            description:             $model->description,
            category:                AssetCategory::from($model->category),
            acquisitionDate:         new DateTimeImmutable($model->acquisition_date->format('Y-m-d')),
            acquisitionCost:         (string) $model->acquisition_cost,
            salvageValue:            (string) $model->salvage_value,
            usefulLifeMonths:        (int) $model->useful_life_months,
            depreciationMethod:      DepreciationMethod::from($model->depreciation_method),
            accumulatedDepreciation: (string) $model->accumulated_depreciation,
            bookValue:               (string) $model->book_value,
            branchId:                $model->branch_id,
            costCenterId:            $model->cost_center_id,
            assetAccountId:          $model->asset_account_id,
            accumDeprAccountId:      $model->accum_depr_account_id,
            deprExpenseAccountId:    $model->depr_expense_account_id,
        );

        $asset->status = $model->status;

        if ($model->disposed_at) {
            $asset->disposedAt = new DateTimeImmutable($model->disposed_at->format('Y-m-d H:i:sP'));
        }

        $asset->disposalProceeds        = $model->disposal_proceeds !== null ? (string) $model->disposal_proceeds : null;
        $asset->disposalGainLoss        = $model->disposal_gain_loss !== null ? (string) $model->disposal_gain_loss : null;
        $asset->disposalJournalEntryId  = $model->disposal_journal_entry_id;

        return $asset;
    }
}
