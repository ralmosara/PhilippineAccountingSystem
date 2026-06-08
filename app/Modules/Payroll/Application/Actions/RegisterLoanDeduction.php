<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Application\Contracts\LoanDeductionRepositoryContract;
use App\Modules\Payroll\Domain\Entities\LoanDeduction;
use App\Modules\Payroll\Domain\ValueObjects\LoanType;
use DateTimeImmutable;
use DomainException;
use Ramsey\Uuid\Uuid;

/**
 * Registers a new SSS salary loan or HDMF (Pag-IBIG) loan for an employee.
 *
 * Business rules:
 *  - originalAmount   must be > 0 (BCMath)
 *  - monthlyAmortization must be > 0 (BCMath)
 *  - outstandingBalance is initialised to originalAmount on creation
 */
final readonly class RegisterLoanDeduction
{
    public function __construct(
        private LoanDeductionRepositoryContract $repository,
    ) {
    }

    public function execute(
        string   $employeeId,
        string   $companyId,
        LoanType $loanType,
        string   $loanReference,
        string   $originalAmount,
        string   $monthlyAmortization,
        string   $startedOn,
        ?string  $endsOn,
        string   $actorId,          // reserved for future audit trail
    ): LoanDeduction {
        if (bccomp($originalAmount, '0', 2) <= 0) {
            throw new DomainException('Original amount must be greater than zero.');
        }

        if (bccomp($monthlyAmortization, '0', 2) <= 0) {
            throw new DomainException('Monthly amortization must be greater than zero.');
        }

        $loan = new LoanDeduction(
            id:                  Uuid::uuid4()->toString(),
            companyId:           $companyId,
            employeeId:          $employeeId,
            loanType:            $loanType,
            loanReference:       $loanReference,
            originalAmount:      $originalAmount,
            outstandingBalance:  $originalAmount,   // starts equal to principal
            monthlyAmortization: $monthlyAmortization,
            startedOn:           new DateTimeImmutable($startedOn),
            endsOn:              $endsOn !== null ? new DateTimeImmutable($endsOn) : null,
            isActive:            true,
            notes:               null,
        );

        $this->repository->save($loan);

        return $loan;
    }
}
