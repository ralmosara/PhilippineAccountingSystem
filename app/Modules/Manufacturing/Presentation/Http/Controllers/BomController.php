<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Controllers;

use App\Modules\Manufacturing\Application\Actions\CreateBillOfMaterials;
use App\Modules\Manufacturing\Domain\ValueObjects\BomId;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\BomModel;
use App\Modules\Manufacturing\Presentation\Http\Requests\StoreBomRequest;
use App\Modules\Manufacturing\Presentation\Http\Resources\BomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Resource controller — only the 7 RESTful methods.
 * Single-action verbs (activate BOM) get their own invokable controller.
 */
final class BomController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BomModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = '%' . $request->string('q') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'ilike', $term)
                  ->orWhere('name', 'ilike', $term)
                  ->orWhere('item_name', 'ilike', $term);
            });
        }

        return BomResource::collection(
            $query->orderByDesc('created_at')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $bom): BomResource
    {
        $model = BomModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($bom);

        return new BomResource($model);
    }

    public function create(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use GET /api/v1/items to populate the finished-good selector and component list.',
        ]);
    }

    public function store(StoreBomRequest $request, CreateBillOfMaterials $action): JsonResponse
    {
        $bom = $action->execute(
            companyId:            $request->user()->company_id,
            itemId:               $request->string('item_id')->toString(),
            itemName:             $request->string('item_name')->toString(),
            code:                 $request->string('code')->toString(),
            name:                 $request->string('name')->toString(),
            version:              $request->string('version', '1.0')->toString(),
            standardBatchSize:    (string) $request->input('standard_batch_size'),
            laborCostPerBatch:    (string) $request->input('labor_cost_per_batch'),
            overheadCostPerBatch: (string) $request->input('overhead_cost_per_batch'),
            lines:                $request->validated('lines'),
            notes:                $request->input('notes'),
            actorId:              $request->user()->id,
        );

        return new JsonResponse(new BomResource($bom), 201);
    }

    public function edit(Request $request, string $bom): JsonResponse
    {
        $model = BomModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($bom);

        if ($model->status !== 'draft') {
            return new JsonResponse([
                'message' => 'Only draft BOMs may be edited.',
            ], 423);
        }

        return new JsonResponse(['bom' => new BomResource($model)]);
    }

    public function update(Request $request, string $bom): JsonResponse
    {
        $model = BomModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($bom);

        if ($model->status !== 'draft') {
            return new JsonResponse([
                'message' => 'Only draft BOMs may be updated. Supersede the active BOM and create a new version.',
            ], 423);
        }

        $model->update($request->only([
            'item_name', 'name', 'version',
            'standard_batch_size', 'labor_cost_per_batch',
            'overhead_cost_per_batch', 'notes',
        ]));

        $model->load('lines');

        return new JsonResponse(new BomResource($model));
    }

    public function destroy(Request $request, string $bom): JsonResponse
    {
        $model = BomModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($bom);

        if ($model->status === 'active') {
            return new JsonResponse([
                'message' => 'Active BOMs cannot be deleted; supersede them instead.',
            ], 423);
        }

        // Soft-delete: mark as superseded (no hard DELETE)
        $model->update(['status' => 'superseded']);

        return new JsonResponse(null, 204);
    }
}
