<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Application\Actions\RecordStockMovement;
use App\Modules\Inventory\Application\Exceptions\ItemNotFoundException;
use App\Modules\Inventory\Domain\Exceptions\NegativeStockException;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\StockMovementModel;
use App\Modules\Inventory\Presentation\Http\Requests\RecordStockMovementRequest;
use App\Modules\Inventory\Presentation\Http\Resources\StockMovementResource;
use Illuminate\Http\JsonResponse;

final class RecordStockMovementController
{
    public function __invoke(
        RecordStockMovementRequest $request,
        RecordStockMovement $action,
    ): JsonResponse {
        try {
            $movement = $action->execute(
                itemId:        $request->string('item_id')->toString(),
                warehouseId:   $request->string('warehouse_id')->toString(),
                movementType:  MovementType::from($request->string('movement_type')->toString()),
                quantity:      $request->string('quantity')->toString(),
                unitCost:      $request->filled('unit_cost')
                                   ? Money::php($request->string('unit_cost')->toString())
                                   : null,
                movedAt:       null,
                sourceDocId:   $request->input('source_doc_id'),
                sourceDocType: $request->input('source_doc_type'),
                projectId:     $request->input('project_id'),
                remarks:       $request->input('remarks'),
                actorId:       $request->user()->id,
            );
        } catch (ItemNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (NegativeStockException $e) {
            return new JsonResponse([
                'message'   => $e->getMessage(),
                'available' => $e->available,
                'requested' => $e->requested,
            ], 422);
        }

        $model = StockMovementModel::with(['item', 'warehouse'])->findOrFail($movement->id);
        return new JsonResponse(new StockMovementResource($model), 201);
    }
}
