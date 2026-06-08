<?php

declare(strict_types=1);

namespace App\Modules\Hr\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Hr\Application\Contracts\EmployeeRepositoryContract;
use App\Modules\Hr\Domain\Entities\Employee;
use App\Modules\Hr\Domain\ValueObjects\EmployeeId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CreateEmployee
{
    public function __construct(
        private EmployeeRepositoryContract $employees,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     middle_name?: string|null,
     *     tin?: string|null,
     *     sss_no?: string|null,
     *     philhealth_no?: string|null,
     *     pagibig_no?: string|null,
     *     hired_on: string,
     *     employment_status?: string,
     *     department_id?: string|null,
     *     position_id?: string|null,
     *     email?: string|null,
     * }  $data
     */
    public function execute(string $companyId, array $data, string $actorId): Employee
    {
        return DB::transaction(function () use ($companyId, $data, $actorId) {
            $employee = new Employee(
                id:                EmployeeId::generate(),
                companyId:         $companyId,
                employeeNo:        $this->employees->nextEmployeeNo($companyId),
                firstName:         $data['first_name'],
                lastName:          $data['last_name'],
                middleName:        $data['middle_name'] ?? null,
                tin:               $data['tin'] ?? null,
                sssNo:             $data['sss_no'] ?? null,
                philhealthNo:      $data['philhealth_no'] ?? null,
                pagibigNo:         $data['pagibig_no'] ?? null,
                hiredOn:           new DateTimeImmutable($data['hired_on']),
                separatedOn:       null,
                employmentStatus:  $data['employment_status'] ?? 'probationary',
                departmentId:      $data['department_id'] ?? null,
                positionId:        $data['position_id']   ?? null,
                email:             $data['email'] ?? null,
                isActive:          true,
            );

            $this->employees->save($employee);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'employee.created',
                aggregate:   'Employee',
                aggregateId: $employee->id->value,
                payload: [
                    'employee_no' => $employee->employeeNo,
                    'name'        => $employee->fullName(),
                    'hired_on'    => $employee->hiredOn->format('Y-m-d'),
                    'status'      => $employee->employmentStatus,
                ],
            );

            return $employee;
        });
    }
}
