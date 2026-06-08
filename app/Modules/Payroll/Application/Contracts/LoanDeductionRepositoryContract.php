<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Contracts;

use App\Modules\Payroll\Domain\Entities\LoanDeduction;
use DateTimeImmutable;

interface LoanDeductionRepositoryContract
{
    /**
     * Return all loans that are active as-of the given date for an employee.
     * Equivalent to:
     *   WHERE employee_id = ? AND is_active = true
     *     AND started_on <= ?
     *     AND (ends_on IS NULL OR ends_on >= ?)
     *
     * @return LoanDeduction[]
     */
    public function findActiveForEmployee(string $employeeId, DateTimeImmutable $asOf): array;

    /**
     * Persist a new or updated LoanDeduction (upsert by id).
     */
    public function save(LoanDeduction $loan): void;

    /**
     * Find a single loan by its UUID, or null if not found.
     */
    public function findById(string $id): ?LoanDeduction;

    /**
     * Return all loans (active and settled) for an employee.
     *
     * @return LoanDeduction[]
     */
    public function findForEmployee(string $employeeId): array;
}
