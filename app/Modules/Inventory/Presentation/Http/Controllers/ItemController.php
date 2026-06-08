<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Modules\Inventory\Application\Actions\CreateItem;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\ItemModel;
use App\Modules\Inventory\Presentation\Http\Requests\StoreItemRequest;
use App\Modules\Inventory\Presentation\Http\Resources\ItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Item resource — 7 RESTful methods only. */
final class ItemController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ItemModel::query()
            ->with(['uom', 'category'])
            ->where('company_id', $request->user()->company_id);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }
        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $term)
                ->orWhere('sku', 'ilike', $term));
        }

        return ItemResource::collection(
            $query->orderBy('sku')->paginate($request->integer('per_page', 50))
        );
    }

    public function show(Request $request, string $item): ItemResource
    {
        return new ItemResource(
            ItemModel::query()
                ->with(['uom', 'category'])
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($item)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'kinds'           => ['stock', 'service', 'raw_material', 'fg', 'wip', 'asset'],
            'costing_methods' => ['moving_average', 'fifo', 'standard'],
        ]);
    }

    public function store(StoreItemRequest $request, CreateItem $action): JsonResponse
    {
        $item = $action->execute(
            companyId: $request->user()->company_id,
            data:      $request->validated(),
            actorId:   $request->user()->id,
        );

        $model = ItemModel::with(['uom', 'category'])->findOrFail($item->id->value);
        return new JsonResponse(new ItemResource($model), 201);
    }

    public function edit(Request $request, string $item): ItemResource
    {
        return $this->show($request, $item);
    }

    public function update(Request $request, string $item): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdateItem action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $item): JsonResponse
    {
        // Soft-deactivate; never hard-delete (BIR audit trail)
        ItemModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $item)
            ->update(['is_active' => false]);

        return new JsonResponse(null, 204);
    }
}
