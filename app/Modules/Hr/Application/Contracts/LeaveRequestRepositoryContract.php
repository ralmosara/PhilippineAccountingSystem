<?php

declare(strict_types=1);

namespace App\Modules\Hr\Application\Contracts;

use App\Modules\Hr\Domain\Entities\LeaveRequest;
use App\Modules\Hr\Domain\ValueObjects\LeaveRequestId;

interface LeaveRequestRepositoryContract
{
    public function findById(LeaveRequestId $id): LeaveRequest;

    public function save(LeaveRequest $request): void;

    /**
     * @return list<LeaveRequest>
     */
    public function findPendingForEmployee(string $employeeId): array;
}
