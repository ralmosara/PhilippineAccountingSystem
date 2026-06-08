<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Application\Actions\AdjustStock;
use App\Modules\Inventory\Application\Exceptions\ItemNotFoundException;
use App\Modules\Inventory\Domain\Exceptions\NegativeStockException;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\StockMovementModel;
use App\Modules\Inventory\Presentation\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Presentation\Http\Resources\StockMovementResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/stock/adjust   (MFA required) */
final class AdjustStockController
{
    public function __invoke(
        AdjustStockRequest $request,
        AdjustStock $action,
    ): JsonResponse {
        try {
            $movement = $action->execute(
                itemId:      $request->string('item_id')->toString(),
                warehouseId: $request->string('warehouse_id')->toString(),
                quantity:    $request->string('quantity')->toString(),
                isPositive:  $request->boolean('is_positive'),
                unitCost:    Money::php($request->string('unit_cost')->toString()),
                reason:      $request->string('reason')->toString(),
                actorId:     $request->user()->id,
            );
        } catch (ItemNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (NegativeStockException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        $model = StockMovementModel::with(['item', 'warehouse'])->findOrFail($movement->id);
        return new JsonResponse(new StockMovementResource($model), 201);
    }
}
