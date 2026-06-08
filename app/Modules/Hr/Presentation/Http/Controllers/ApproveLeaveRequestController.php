<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Controllers;

use App\Modules\Hr\Application\Actions\ApproveLeaveRequest;
use App\Modules\Hr\Application\Exceptions\LeaveRequestNotFoundException;
use App\Modules\Hr\Infrastructure\Persistence\Eloquent\LeaveRequestModel;
use App\Modules\Hr\Presentation\Http\Requests\ApproveLeaveRequestRequest;
use App\Modules\Hr\Presentation\Http\Resources\LeaveRequestResource;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Single-action invokable controller.
 *
 *   POST /api/v1/leave-requests/{leaveRequest}/approve
 */
final class ApproveLeaveRequestController
{
    public function __invoke(
        ApproveLeaveRequestRequest $request,
        ApproveLeaveRequest $action,
        string $leaveRequest,
    ): JsonResponse {
        try {
            $result = $action->execute(
                leaveRequestId: $leaveRequest,
                approverId:     $request->user()->id,
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
