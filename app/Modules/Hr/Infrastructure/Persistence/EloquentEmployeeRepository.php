<?php

declare(strict_types=1);

namespace App\Modules\Hr\Infrastructure\Persistence;

use App\Modules\Hr\Application\Contracts\EmployeeRepositoryContract;
use App\Modules\Hr\Domain\Entities\Employee;
use App\Modules\Hr\Domain\ValueObjects\EmployeeId;
use App\Modules\Hr\Infrastructure\Persistence\Eloquent\EmployeeModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Crypt;

final class EloquentEmployeeRepository implements EmployeeRepositoryContract
{
    public function findById(EmployeeId $id): ?Employee
    {
        $model = EmployeeModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(Employee $employee): void
    {
        EmployeeModel::query()->updateOrInsert(
            ['id' => $employee->id->value],
            [
                'company_id'              => $employee->companyId,
                'employee_no'             => $employee->employeeNo,
                'tin_encrypted'           => $employee->tin ? Crypt::encryptString($employee->tin) : null,
                'sss_no_encrypted'        => $employee->sssNo ? Crypt::encryptString($employee->sssNo) : null,
                'philhealth_no_encrypted' => $employee->philhealthNo ? Crypt::encryptString($employee->philhealthNo) : null,
                'pagibig_no_encrypted'    => $employee->pagibigNo ? Crypt::encryptString($employee->pagibigNo) : null,
                'first_name'              => $employee->firstName,
                'middle_name'             => $employee->middleName,
                'last_name'                => $employee->lastName,
                'hired_on'                => $employee->hiredOn,
                'separated_on'             => $employee->separatedOn,
                'employment_status'       => $employee->employmentStatus,
                'department_id'           => $employee->departmentId,
                'position_id'             => $employee->positionId,
                'email'                   => $employee->email,
                'is_active'               => $employee->isActive,
                'updated_at'              => now(),
                'created_at'              => now(),
            ],
        );
    }

    public function nextEmployeeNo(string $companyId): string
    {
        $count = EmployeeModel::query()->where('company_id', $companyId)->count();
        return 'EMP-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    public function listActiveForCompany(string $companyId): array
    {
        return EmployeeModel::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('separated_on')
            ->orderBy('last_name')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(EmployeeModel $m): Employee
    {
        return new Employee(
            id:                new EmployeeId($m->id),
            companyId:         $m->company_id,
            employeeNo:        $m->employee_no,
            firstName:         $m->first_name,
            lastName:          $m->last_name,
            middleName:        $m->middle_name,
            tin:               $m->tin(),
            sssNo:             $m->sssNo(),
            philhealthNo:      $m->philhealthNo(),
            pagibigNo:         $m->pagibigNo(),
            hiredOn:           new DateTimeImmutable($m->hired_on->toIso8601String()),
            separatedOn:       $m->separated_on ? new DateTimeImmutable($m->separated_on->toIso8601String()) : null,
            employmentStatus:  $m->employment_status,
            departmentId:      $m->department_id,
            positionId:        $m->position_id,
            email:             $m->email,
            isActive:          (bool) $m->is_active,
        );
    }
}
