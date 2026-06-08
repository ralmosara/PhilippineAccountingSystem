<?php

declare(strict_types=1);

namespace App\Modules\Hr\Application\Actions;

use App\Modules\Hr\Application\Contracts\LeaveRequestRepositoryContract;
use App\Modules\Hr\Application\Exceptions\LeaveRequestNotFoundException;
use App\Modules\Hr\Domain\Entities\LeaveRequest;
use App\Modules\Hr\Domain\ValueObjects\LeaveRequestId;
use DateTimeImmutable;
use DomainException;

final readonly class RejectLeaveRequest
{
    public function __construct(
        private LeaveRequestRepositoryContract $leaveRequests,
    ) {
    }

    public function execute(string $leaveRequestId, string $rejectorId, string $reason): LeaveRequest
    {
        $id = LeaveRequestId::from($leaveRequestId);

        try {
            $leaveRequest = $this->leaveRequests->findById($id);
        } catch (\RuntimeException) {
            throw new LeaveRequestNotFoundException($leaveRequestId);
        }

        if ($leaveRequest->status !== 'pending') {
            throw new DomainException(
                "Leave request {$leaveRequestId} cannot be rejected: current status is '{$leaveRequest->status}'."
            );
        }

        $now = new DateTimeImmutable();

        $rejected = new LeaveRequest(
            id:              $leaveRequest->id,
            companyId:       $leaveRequest->companyId,
            employeeId:      $leaveRequest->employeeId,
            leaveType:       $leaveRequest->leaveType,
            startDate:       $leaveRequest->startDate,
            endDate:         $leaveRequest->endDate,
            daysRequested:   $leaveRequest->daysRequested,
            reason:          $leaveRequest->reason,
            status:          'rejected',
            approvedBy:      null,
            approvedAt:      null,
            rejectionReason: $reason,
            createdAt:       $leaveRequest->createdAt,
            updatedAt:       $now,
        );

        $this->leaveRequests->save($rejected);

        return $rejected;
    }
}
