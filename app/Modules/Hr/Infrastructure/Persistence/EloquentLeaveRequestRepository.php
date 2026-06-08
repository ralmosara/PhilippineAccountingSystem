<?php

declare(strict_types=1);

namespace App\Modules\Hr\Infrastructure\Persistence;

use App\Modules\Hr\Application\Contracts\LeaveRequestRepositoryContract;
use App\Modules\Hr\Application\Exceptions\LeaveRequestNotFoundException;
use App\Modules\Hr\Domain\Entities\LeaveRequest;
use App\Modules\Hr\Domain\ValueObjects\LeaveRequestId;
use App\Modules\Hr\Infrastructure\Persistence\Eloquent\LeaveRequestModel;
use DateTimeImmutable;

final class EloquentLeaveRequestRepository implements LeaveRequestRepositoryContract
{
    public function findById(LeaveRequestId $id): LeaveRequest
    {
        $model = LeaveRequestModel::query()->find($id->value);

        if ($model === null) {
            throw new LeaveRequestNotFoundException($id->value);
        }

        return $this->toDomain($model);
    }

    public function save(LeaveRequest $request): void
    {
        LeaveRequestModel::query()->updateOrInsert(
            ['id' => $request->id->value],
            [
                'company_id'       => $request->companyId,
                'employee_id'      => $request->employeeId,
                'leave_type'       => $request->leaveType,
                'start_date'       => $request->startDate->format('Y-m-d'),
                'end_date'         => $request->endDate->format('Y-m-d'),
                'days_requested'   => $request->daysRequested,
                'reason'           => $request->reason,
                'status'           => $request->status,
                'approved_by'      => $request->approvedBy,
                'approved_at'      => $request->approvedAt?->format('Y-m-d H:i:sP'),
                'rejection_reason' => $request->rejectionReason,
                'created_at'       => $request->createdAt->format('Y-m-d H:i:sP'),
                'updated_at'       => $request->updatedAt->format('Y-m-d H:i:sP'),
            ],
        );
    }

    /**
     * @return list<LeaveRequest>
     */
    public function findPendingForEmployee(string $employeeId): array
    {
        return LeaveRequestModel::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(LeaveRequestModel $m): LeaveRequest
    {
        return new LeaveRequest(
            id:              new LeaveRequestId($m->id),
            companyId:       $m->company_id,
            employeeId:      $m->employee_id,
            leaveType:       $m->leave_type,
            startDate:       new DateTimeImmutable($m->start_date->toIso8601String()),
            endDate:         new DateTimeImmutable($m->end_date->toIso8601String()),
            daysRequested:   (string) $m->days_requested,
            reason:          $m->reason,
            status:          $m->status,
            approvedBy:      $m->approved_by,
            approvedAt:      $m->approved_at ? new DateTimeImmutable($m->approved_at->toIso8601String()) : null,
            rejectionReason: $m->rejection_reason,
            createdAt:       new DateTimeImmutable($m->created_at->toIso8601String()),
            updatedAt:       new DateTimeImmutable($m->updated_at->toIso8601String()),
        );
    }
}
