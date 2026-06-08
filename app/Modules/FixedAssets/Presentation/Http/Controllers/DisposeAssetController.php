<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Controllers;

use App\Modules\FixedAssets\Application\Actions\DisposeAsset;
use App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent\FixedAssetModel;
use App\Modules\FixedAssets\Presentation\Http\Requests\DisposeAssetRequest;
use App\Modules\FixedAssets\Presentation\Http\Resources\FixedAssetResource;
use Illuminate\Http\JsonResponse;

/**
 * Single-action controller — POST /fixed-assets/{asset}/dispose
 *
 * MFA-gated. Posts the disposal JV and marks the asset as disposed.
 */
final class DisposeAssetController
{
    public function __invoke(
        DisposeAssetRequest $request,
        DisposeAsset        $action,
        string              $asset,
    ): JsonResponse {
        $validated = $request->validated();

        $action->execute(
            assetId:      $asset,
            proceeds:     (string) $validated['proceeds'],
            disposalDate: $validated['disposal_date'],
            accountIds: [
                'cash_account_id'     => $validated['cash_account_id'],
                'gain_loss_account_id'=> $validated['gain_loss_account_id'],
            ],
            actorId: $request->user()->id,
        );

        $model = FixedAssetModel::query()->findOrFail($asset);

        return (new FixedAssetResource($model))
            ->response()
            ->setStatusCode(200);
    }
}
