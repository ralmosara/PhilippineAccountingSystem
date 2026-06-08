<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence;

use App\Modules\Payroll\Application\Contracts\LoanDeductionRepositoryContract;
use App\Modules\Payroll\Domain\Entities\LoanDeduction;
use App\Modules\Payroll\Domain\ValueObjects\LoanType;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\LoanDeductionModel;
use DateTimeImmutable;

final class EloquentLoanDeductionRepository implements LoanDeductionRepositoryContract
{
    /**
     * {@inheritdoc}
     *
     * @return LoanDeduction[]
     */
    public function findActiveForEmployee(string $employeeId, DateTimeImmutable $asOf): array
    {
        $asOfStr = $asOf->format('Y-m-d');

        return LoanDeductionModel::query()
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->where('started_on', '<=', $asOfStr)
            ->where(function ($q) use ($asOfStr) {
                $q->whereNull('ends_on')
                  ->orWhere('ends_on', '>=', $asOfStr);
            })
            ->get()
            ->map(fn (LoanDeductionModel $m) => $this->toDomain($m))
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function save(LoanDeduction $loan): void
    {
        LoanDeductionModel::query()->updateOrInsert(
            ['id' => $loan->id],
            [
                'company_id'           => $loan->companyId,
                'employee_id'          => $loan->employeeId,
                'loan_type'            => $loan->loanType->value,
                'loan_reference'       => $loan->loanReference,
                'original_amount'      => $loan->originalAmount,
                'outstanding_balance'  => $loan->outstandingBalance,
                'monthly_amortization' => $loan->monthlyAmortization,
                'started_on'           => $loan->startedOn->format('Y-m-d'),
                'ends_on'              => $loan->endsOn?->format('Y-m-d'),
                'is_active'            => $loan->isActive,
                'notes'                => $loan->notes,
                'updated_at'           => now(),
                'created_at'           => now(),
            ],
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findById(string $id): ?LoanDeduction
    {
        $model = LoanDeductionModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     *
     * @return LoanDeduction[]
     */
    public function findForEmployee(string $employeeId): array
    {
        return LoanDeductionModel::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('started_on')
            ->get()
            ->map(fn (LoanDeductionModel $m) => $this->toDomain($m))
            ->all();
    }

    // -------------------------------------------------------------------------
    // Mapping
    // -------------------------------------------------------------------------

    private function toDomain(LoanDeductionModel $model): LoanDeduction
    {
        return new LoanDeduction(
            id:                  $model->id,
            companyId:           $model->company_id,
            employeeId:          $model->employee_id,
            loanType:            LoanType::from($model->loan_type),
            loanReference:       $model->loan_reference,
            originalAmount:      (string) $model->original_amount,
            outstandingBalance:  (string) $model->outstanding_balance,
            monthlyAmortization: (string) $model->monthly_amortization,
            startedOn:           new DateTimeImmutable($model->started_on->format('Y-m-d')),
            endsOn:              $model->ends_on !== null
                                     ? new DateTimeImmutable($model->ends_on->format('Y-m-d'))
                                     : null,
            isActive:            (bool) $model->is_active,
            notes:               $model->notes,
        );
    }
}
