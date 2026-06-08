<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Controllers;

use App\Modules\Hr\Application\Actions\SubmitLeaveRequest;
use App\Modules\Hr\Infrastructure\Persistence\Eloquent\LeaveRequestModel;
use App\Modules\Hr\Presentation\Http\Requests\StoreLeaveRequestRequest;
use App\Modules\Hr\Presentation\Http\Resources\LeaveRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Leave request resource — 7 RESTful methods only. */
final class LeaveRequestController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LeaveRequestModel::query()
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->string('employee_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->string('leave_type'));
        }

        return LeaveRequestResource::collection(
            $query->orderBy('created_at', 'desc')
                  ->paginate(25)
        );
    }

    public function show(Request $request, string $leaveRequest): LeaveRequestResource
    {
        return new LeaveRequestResource(
            LeaveRequestModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($leaveRequest)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'leave_types' => ['sick', 'vacation', 'emergency', 'maternity', 'paternity', 'solo_parent', 'bereavement'],
            'statuses'    => ['pending', 'approved', 'rejected', 'cancelled'],
        ]);
    }

    public function store(StoreLeaveRequestRequest $request, SubmitLeaveRequest $action): JsonResponse
    {
        $leaveRequest = $action->execute(
            companyId:  $request->user()->company_id,
            employeeId: $request->validated('employee_id'),
            leaveType:  $request->validated('leave_type'),
            startDate:  $request->validated('start_date'),
            endDate:    $request->validated('end_date'),
            reason:     $request->validated('reason'),
            actorId:    $request->user()->id,
        );

        $model = LeaveRequestModel::findOrFail($leaveRequest->id->value);
        return new JsonResponse(new LeaveRequestResource($model), 201);
    }

    public function edit(Request $request, string $leaveRequest): LeaveRequestResource
    {
        return $this->show($request, $leaveRequest);
    }

    public function update(Request $request, string $leaveRequest): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdateLeaveRequest action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $leaveRequest): JsonResponse
    {
        $model = LeaveRequestModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($leaveRequest);

        if ($model->status !== 'pending') {
            return new JsonResponse(
                ['message' => 'Only pending leave requests can be cancelled.'],
                422
            );
        }

        $model->update(['status' => 'cancelled']);

        return new JsonResponse(null, 204);
    }
}
