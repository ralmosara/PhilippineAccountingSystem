<?php

declare(strict_types=1);

namespace App\Modules\Hr\Domain\Entities;

use App\Modules\Hr\Domain\ValueObjects\EmployeeId;
use DateTimeImmutable;

final readonly class Employee
{
    public function __construct(
        public EmployeeId $id,
        public string $companyId,
        public string $employeeNo,
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?string $tin,                              // decrypted on read
        public ?string $sssNo,
        public ?string $philhealthNo,
        public ?string $pagibigNo,
        public DateTimeImmutable $hiredOn,
        public ?DateTimeImmutable $separatedOn,
        public string $employmentStatus,                  // probationary | regular | ...
        public ?string $departmentId,
        public ?string $positionId,
        public ?string $email,
        public bool $isActive,
    ) {
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->firstName, $this->middleName, $this->lastName])));
    }

    public function isCurrentlyEmployed(): bool
    {
        return $this->isActive && $this->separatedOn === null;
    }
}
