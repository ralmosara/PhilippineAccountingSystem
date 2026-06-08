<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Controllers;

use App\Modules\Manufacturing\Application\Actions\CancelWorkOrder;
use App\Modules\Manufacturing\Presentation\Http\Resources\WorkOrderResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action invokable controller (Taylor Otwell convention).
 *
 *   POST /api/v1/work-orders/{order}/cancel
 */
final class CancelWorkOrderController
{
    public function __invoke(
        Request $request,
        CancelWorkOrder $action,
        string $order,
    ): JsonResponse {
        try {
            $workOrder = $action->execute(
                workOrderId: $order,
                actorId:     $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(new WorkOrderResource($workOrder), 200);
    }
}
