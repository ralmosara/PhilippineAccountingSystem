<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Controllers;

use App\Modules\Manufacturing\Application\Actions\CompleteProductionRun;
use App\Modules\Manufacturing\Presentation\Http\Requests\CompleteProductionRunRequest;
use App\Modules\Manufacturing\Presentation\Http\Resources\WorkOrderResource;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Single-action invokable controller (Taylor Otwell convention).
 *
 *   POST /api/v1/work-orders/{order}/complete
 *
 * Requires MFA middleware — this action posts a journal entry and consumes stock.
 */
final class CompleteProductionRunController
{
    public function __invoke(
        CompleteProductionRunRequest $request,
        CompleteProductionRun $action,
        string $order,
    ): JsonResponse {
        try {
            $workOrder = $action->execute(
                workOrderId:      $order,
                quantityProduced: (string) $request->input('quantity_produced'),
                actorId:          $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(new WorkOrderResource($workOrder), 200);
    }
}
