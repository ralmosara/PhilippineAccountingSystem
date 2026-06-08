<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Application\Contracts\LoanDeductionRepositoryContract;
use App\Modules\Payroll\Domain\Entities\LoanDeduction;
use DateTimeImmutable;
use DomainException;

/**
 * Settles (closes) an active loan deduction — marks it inactive and sets
 * ends_on to today. Used when a loan is fully paid off or manually closed
 * by payroll staff.
 *
 * Loans are never hard-deleted; the record is retained for audit purposes.
 */
final readonly class SettleLoanDeduction
{
    public function __construct(
        private LoanDeductionRepositoryContract $repository,
    ) {
    }

    public function execute(string $loanId, string $actorId): LoanDeduction
    {
        $loan = $this->repository->findById($loanId);

        if ($loan === null) {
            throw new DomainException("Loan deduction [{$loanId}] not found.");
        }

        if (! $loan->isActive) {
            throw new DomainException("Loan deduction [{$loanId}] is already settled.");
        }

        $today = new DateTimeImmutable('today');

        // Reconstruct as settled — domain entity is readonly, so we create a
        // new instance with updated isActive / endsOn.
        $settled = new LoanDeduction(
            id:                  $loan->id,
            companyId:           $loan->companyId,
            employeeId:          $loan->employeeId,
            loanType:            $loan->loanType,
            loanReference:       $loan->loanReference,
            originalAmount:      $loan->originalAmount,
            outstandingBalance:  $loan->outstandingBalance,
            monthlyAmortization: $loan->monthlyAmortization,
            startedOn:           $loan->startedOn,
            endsOn:              $today,
            isActive:            false,
            notes:               $loan->notes,
        );

        $this->repository->save($settled);

        return $settled;
    }
}
