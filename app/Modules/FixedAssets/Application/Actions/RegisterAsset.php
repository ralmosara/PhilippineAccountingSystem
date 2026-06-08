<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\FixedAssets\Application\Contracts\FixedAssetRepositoryContract;
use App\Modules\FixedAssets\Domain\Entities\FixedAsset;
use App\Modules\FixedAssets\Domain\Events\AssetRegistered;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetCategory;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetId;
use App\Modules\FixedAssets\Domain\ValueObjects\DepreciationMethod;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Register a new fixed asset.
 *
 * - Assigns the next sequential asset number (SELECT … FOR UPDATE safe).
 * - Sets book_value = acquisition_cost at creation.
 * - Audits 'asset.registered'.
 */
final readonly class RegisterAsset
{
    public function __construct(
        private FixedAssetRepositoryContract $assets,
        private AuditWriterContract          $audit,
        private Dispatcher                   $events,
    ) {
    }

    /**
     * @param  array{
     *     company_id: string,
     *     branch_id?: string|null,
     *     cost_center_id?: string|null,
     *     name: string,
     *     description?: string|null,
     *     category: string,
     *     acquisition_date: string,
     *     acquisition_cost: string,
     *     salvage_value?: string,
     *     useful_life_months: int,
     *     depreciation_method: string,
     *     asset_account_id?: string|null,
     *     accum_depr_account_id?: string|null,
     *     depr_expense_account_id?: string|null,
     * }  $data
     */
    public function execute(array $data, string $actorId): FixedAsset
    {
        return DB::transaction(function () use ($data, $actorId) {
            $companyId = $data['company_id'];
            $assetNo   = $this->assets->nextAssetNo($companyId);

            $cost         = $data['acquisition_cost'];
            $salvageValue = $data['salvage_value'] ?? '0.00';

            // book_value = acquisition_cost at registration (no depreciation yet)
            $bookValue = $cost;

            $asset = new FixedAsset(
                id:                    AssetId::generate(),
                companyId:             $companyId,
                assetNo:               $assetNo,
                name:                  $data['name'],
                description:           $data['description']    ?? null,
                category:              AssetCategory::from($data['category']),
                acquisitionDate:       new DateTimeImmutable($data['acquisition_date']),
                acquisitionCost:       $cost,
                salvageValue:          $salvageValue,
                usefulLifeMonths:      (int) $data['useful_life_months'],
                depreciationMethod:    DepreciationMethod::from($data['depreciation_method']),
                accumulatedDepreciation: '0.00',
                bookValue:             $bookValue,
                branchId:              $data['branch_id']              ?? null,
                costCenterId:          $data['cost_center_id']         ?? null,
                assetAccountId:        $data['asset_account_id']       ?? null,
                accumDeprAccountId:    $data['accum_depr_account_id']  ?? null,
                deprExpenseAccountId:  $data['depr_expense_account_id'] ?? null,
            );

            $this->assets->save($asset);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'asset.registered',
                aggregate:   'FixedAsset',
                aggregateId: $asset->id->value,
                payload: [
                    'asset_no'         => $assetNo,
                    'name'             => $asset->name,
                    'category'         => $asset->category->value,
                    'acquisition_date' => $asset->acquisitionDate->format('Y-m-d'),
                    'acquisition_cost' => $asset->acquisitionCost,
                    'salvage_value'    => $asset->salvageValue,
                    'useful_life_months'   => $asset->usefulLifeMonths,
                    'depreciation_method'  => $asset->depreciationMethod->value,
                ],
            );

            $this->events->dispatch(new AssetRegistered(
                assetId:         $asset->id->value,
                companyId:       $companyId,
                assetNo:         $assetNo,
                name:            $asset->name,
                category:        $asset->category->value,
                acquisitionCost: $asset->acquisitionCost,
                registeredBy:    $actorId,
                registeredAt:    new DateTimeImmutable(),
            ));

            return $asset;
        });
    }
}
