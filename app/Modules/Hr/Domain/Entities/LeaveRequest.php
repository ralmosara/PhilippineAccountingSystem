<?php

declare(strict_types=1);

namespace App\Modules\Hr\Domain\Entities;

use App\Modules\Hr\Domain\ValueObjects\LeaveRequestId;
use DateTimeImmutable;

final readonly class LeaveRequest
{
    public function __construct(
        public LeaveRequestId $id,
        public string $companyId,
        public string $employeeId,
        public string $leaveType,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public string $daysRequested,   // stored as numeric string (NUMERIC 5,2)
        public ?string $reason,
        public string $status,
        public ?string $approvedBy,
        public ?DateTimeImmutable $approvedAt,
        public ?string $rejectionReason,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Count of business days (Mon–Fri) between startDate and endDate, inclusive.
     * Does not account for Philippine public holidays.
     */
    public function dayCount(): int
    {
        $count = 0;
        $current = $this->startDate;
        $end = $this->endDate;

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
