<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Resources;

use App\Modules\Payroll\Domain\ValueObjects\LoanType;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\LoanDeductionModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LoanDeductionModel */
final class LoanDeductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $loanType = LoanType::from($this->loan_type);

        return [
            'id'                   => $this->id,
            'employee_id'          => $this->employee_id,
            'loan_type'            => $this->loan_type,
            'loan_type_label'      => $loanType->label(),
            'loan_reference'       => $this->loan_reference,
            'original_amount'      => (string) $this->original_amount,
            'outstanding_balance'  => (string) $this->outstanding_balance,
            'monthly_amortization' => (string) $this->monthly_amortization,
            'started_on'           => $this->started_on?->format('Y-m-d'),
            'ends_on'              => $this->ends_on?->format('Y-m-d'),
            'is_active'            => (bool) $this->is_active,
            'notes'                => $this->notes,
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
