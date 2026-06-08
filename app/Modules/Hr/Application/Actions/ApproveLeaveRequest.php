<?php

declare(strict_types=1);

namespace App\Modules\Hr\Application\Actions;

use App\Modules\Hr\Application\Contracts\LeaveRequestRepositoryContract;
use App\Modules\Hr\Application\Exceptions\LeaveRequestNotFoundException;
use App\Modules\Hr\Domain\Entities\LeaveRequest;
use App\Modules\Hr\Domain\ValueObjects\LeaveRequestId;
use DateTimeImmutable;
use DomainException;

final readonly class ApproveLeaveRequest
{
    public function __construct(
        private LeaveRequestRepositoryContract $leaveRequests,
    ) {
    }

    public function execute(string $leaveRequestId, string $approverId): LeaveRequest
    {
        $id = LeaveRequestId::from($leaveRequestId);

        try {
            $leaveRequest = $this->leaveRequests->findById($id);
        } catch (\RuntimeException) {
            throw new LeaveRequestNotFoundException($leaveRequestId);
        }

        if ($leaveRequest->status !== 'pending') {
            throw new DomainException(
                "Leave request {$leaveRequestId} cannot be approved: current status is '{$leaveRequest->status}'."
            );
        }

        $now = new DateTimeImmutable();

        $approved = new LeaveRequest(
            id:              $leaveRequest->id,
            companyId:       $leaveRequest->companyId,
            employeeId:      $leaveRequest->employeeId,
            leaveType:       $leaveRequest->leaveType,
            startDate:       $leaveRequest->startDate,
            endDate:         $leaveRequest->endDate,
            daysRequested:   $leaveRequest->daysRequested,
            reason:          $leaveRequest->reason,
            status:          'approved',
            approvedBy:      $approverId,
            approvedAt:      $now,
            rejectionReason: null,
            createdAt:       $leaveRequest->createdAt,
            updatedAt:       $now,
        );

        $this->leaveRequests->save($approved);

        return $approved;
    }
}
