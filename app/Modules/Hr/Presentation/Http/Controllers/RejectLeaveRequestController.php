<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Controllers;

use App\Modules\Hr\Application\Actions\RejectLeaveRequest;
use App\Modules\Hr\Application\Exceptions\LeaveRequestNotFoundException;
use App\Modules\Hr\Infrastructure\Persistence\Eloquent\LeaveRequestModel;
use App\Modules\Hr\Presentation\Http\Requests\RejectLeaveRequestRequest;
use App\Modules\Hr\Presentation\Http\Resources\LeaveRequestResource;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Single-action invokable controller.
 *
 *   POST /api/v1/leave-requests/{leaveRequest}/reject
 */
final class RejectLeaveRequestController
{
    public function __invoke(
        RejectLeaveRequestRequest $request,
        RejectLeaveRequest $action,
        string $leaveRequest,
    ): JsonResponse {
        try {
            $result = $action->execute(
                leaveRequestId: $leaveRequest,
                rejectorId:     $request->user()->id,
                reason:         $request->validated('rejection_reason'),
            );
        } catch (LeaveRequestNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        $model = LeaveRequestModel::findOrFail($result->id->value);
        return new JsonResponse(new LeaveRequestResource($model), 200);
    }
}
