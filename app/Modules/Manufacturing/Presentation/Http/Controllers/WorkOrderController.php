<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Controllers;

use App\Modules\Manufacturing\Application\Actions\CreateWorkOrder;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\WorkOrderModel;
use App\Modules\Manufacturing\Presentation\Http\Requests\StoreWorkOrderRequest;
use App\Modules\Manufacturing\Presentation\Http\Resources\WorkOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Resource controller — only the 7 RESTful methods.
 * Non-CRUD verbs (start, complete, cancel) each get their own invokable controller.
 */
final class WorkOrderController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = WorkOrderModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('bom_id')) {
            $query->where('bom_id', $request->string('bom_id'));
        }

        return WorkOrderResource::collection(
            $query->orderByDesc('created_at')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $order): WorkOrderResource
    {
        $model = WorkOrderModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($order);

        return new WorkOrderResource($model);
    }

    public function create(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use GET /api/v1/boms?status=active to list selectable BOMs.',
        ]);
    }

    public function store(StoreWorkOrderRequest $request, CreateWorkOrder $action): JsonResponse
    {
        $workOrder = $action->execute(
            companyId:              $request->user()->company_id,
            bomId:                  $request->string('bom_id')->toString(),
            quantityToProduce:      (string) $request->input('quantity_to_produce'),
            scheduledStart:         $request->input('scheduled_start'),
            scheduledEnd:           $request->input('scheduled_end'),
            warehouseId:            $request->input('warehouse_id'),
            wipAccountId:           $request->input('wip_account_id'),
            finishedGoodsAccountId: $request->input('finished_goods_account_id'),
            rawMaterialsAccountId:  $request->input('raw_materials_account_id'),
            actorId:                $request->user()->id,
        );

        return new JsonResponse(new WorkOrderResource($workOrder), 201);
    }

    public function edit(Request $request, string $order): JsonResponse
    {
        $model = WorkOrderModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($order);

        if ($model->status !== 'draft') {
            return new JsonResponse([
                'message' => 'Only draft work orders may be edited.',
            ], 423);
        }

        return new JsonResponse(['work_order' => new WorkOrderResource($model)]);
    }

    public function update(Request $request, string $order): JsonResponse
    {
        $model = WorkOrderModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($order);

        if ($model->status !== 'draft') {
            return new JsonResponse([
                'message' => 'Only draft work orders may be updated.',
            ], 423);
        }

        $model->update($request->only([
            'quantity_to_produce', 'scheduled_start', 'scheduled_end',
            'warehouse_id', 'wip_account_id',
            'finished_goods_account_id', 'raw_materials_account_id',
        ]));

        $model->load('lines');

        return new JsonResponse(new WorkOrderResource($model));
    }

    public function destroy(Request $request, string $order): JsonResponse
    {
        $model = WorkOrderModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($order);

        if (! in_array($model->status, ['draft', 'cancelled'], true)) {
            return new JsonResponse([
                'message' => 'Only draft or cancelled work orders may be deleted.',
            ], 423);
        }

        // Soft-delete: mark as cancelled (REVOKE DELETE is in place on this table)
        $model->update(['status' => 'cancelled']);

        return new JsonResponse(null, 204);
    }
}
