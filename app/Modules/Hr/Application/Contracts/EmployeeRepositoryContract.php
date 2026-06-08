<?php

declare(strict_types=1);

namespace App\Modules\Hr\Application\Contracts;

use App\Modules\Hr\Domain\Entities\Employee;
use App\Modules\Hr\Domain\ValueObjects\EmployeeId;

interface EmployeeRepositoryContract
{
    public function findById(EmployeeId $id): ?Employee;

    public function save(Employee $employee): void;

    public function nextEmployeeNo(string $companyId): string;

    /**
     * Used by Payroll to enumerate active employees for a payroll run.
     * @return list<Employee>
     */
    public function listActiveForCompany(string $companyId): array;
}
