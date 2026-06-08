<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Controllers;

use App\Modules\FixedAssets\Application\Actions\RegisterAsset;
use App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent\DepreciationEntryModel;
use App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent\FixedAssetModel;
use App\Modules\FixedAssets\Presentation\Http\Requests\StoreFixedAssetRequest;
use App\Modules\FixedAssets\Presentation\Http\Requests\UpdateFixedAssetRequest;
use App\Modules\FixedAssets\Presentation\Http\Resources\DepreciationEntryResource;
use App\Modules\FixedAssets\Presentation\Http\Resources\FixedAssetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Fixed Asset resource controller — max 7 RESTful methods.
 * Compute depreciation and dispose actions are single-action invokable controllers.
 */
final class FixedAssetController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize($request, 'assets.view');

        $query = FixedAssetModel::query();

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->string('company_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return FixedAssetResource::collection(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $asset): FixedAssetResource
    {
        $this->authorize($request, 'assets.view');

        $model = FixedAssetModel::query()
            ->with('depreciationEntries')
            ->findOrFail($asset);

        return (new FixedAssetResource($model))->additional([
            'depreciation_entries' => DepreciationEntryResource::collection(
                $model->depreciationEntries
            ),
        ]);
    }

    public function store(StoreFixedAssetRequest $request, RegisterAsset $action): JsonResponse
    {
        $data              = $request->validated();
        $data['company_id'] = $request->user()->companyId;

        $asset = $action->execute($data, $request->user()->id);

        $model = FixedAssetModel::query()->findOrFail($asset->id->value);

        return (new FixedAssetResource($model))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateFixedAssetRequest $request, string $asset): FixedAssetResource
    {
        $model = FixedAssetModel::query()->findOrFail($asset);

        // Reject edits if depreciation has already been posted
        $hasEntries = DepreciationEntryModel::query()
            ->where('fixed_asset_id', $asset)
            ->exists();

        if ($hasEntries) {
            abort(422, 'Cannot edit an asset after depreciation has been posted.');
        }

        $model->fill($request->validated());
        $model->save();

        return new FixedAssetResource($model->fresh());
    }

    public function destroy(Request $request, string $asset): JsonResponse
    {
        $this->authorize($request, 'assets.register');

        $model = FixedAssetModel::query()->findOrFail($asset);

        // Only allow deletion if no depreciation has been posted
        $hasEntries = DepreciationEntryModel::query()
            ->where('fixed_asset_id', $asset)
            ->exists();

        if ($hasEntries) {
            return new JsonResponse(
                ['message' => 'Cannot delete an asset with depreciation entries. Use disposal instead.'],
                422
            );
        }

        if ($model->status === 'disposed') {
            return new JsonResponse(['message' => 'Cannot delete a disposed asset.'], 422);
        }

        $model->delete();

        return new JsonResponse(null, 204);
    }

    private function authorize(Request $request, string $permission): void
    {
        if (! $request->user()?->can($permission)) {
            abort(403, "Missing permission: {$permission}");
        }
    }
}
