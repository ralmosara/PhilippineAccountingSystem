<?php

declare(strict_types=1);

namespace App\Modules\Hr\Application\Actions;

use App\Modules\Hr\Application\Contracts\LeaveRequestRepositoryContract;
use App\Modules\Hr\Domain\Entities\LeaveRequest;
use App\Modules\Hr\Domain\ValueObjects\LeaveRequestId;
use DateTimeImmutable;

final readonly class SubmitLeaveRequest
{
    public function __construct(
        private LeaveRequestRepositoryContract $leaveRequests,
    ) {
    }

    public function execute(
        string $companyId,
        string $employeeId,
        string $leaveType,
        string $startDate,
        string $endDate,
        ?string $reason,
        string $actorId,
    ): LeaveRequest {
        $start = new DateTimeImmutable($startDate);
        $end   = new DateTimeImmutable($endDate);

        $daysRequested = $this->countBusinessDays($start, $end);

        $now = new DateTimeImmutable();

        $leaveRequest = new LeaveRequest(
            id:              LeaveRequestId::generate(),
            companyId:       $companyId,
            employeeId:      $employeeId,
            leaveType:       $leaveType,
            startDate:       $start,
            endDate:         $end,
            daysRequested:   number_format($daysRequested, 2, '.', ''),
            reason:          $reason,
            status:          'pending',
            approvedBy:      null,
            approvedAt:      null,
            rejectionReason: null,
            createdAt:       $now,
            updatedAt:       $now,
        );

        $this->leaveRequests->save($leaveRequest);

        return $leaveRequest;
    }

    private function countBusinessDays(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $count = 0;
        $current = $start;

        while ($current <= $end) {
            $dow = (int) $current->format('N'); // 1=Mon … 7=Sun
            if ($dow <= 5) {
                $count++;
            }
            $current = $current->modify('+1 day');
        }

        return $count;
    }
}
