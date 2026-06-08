<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterLoanDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.runs.compute') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id'          => ['required', 'uuid'],
            'loan_type'            => ['required', 'string', 'in:sss_salary_loan,hdmf_mpl,hdmf_housing'],
            'loan_reference'       => ['required', 'string', 'max:50'],
            'original_amount'      => ['required', 'numeric', 'min:0.01'],
            'monthly_amortization' => ['required', 'numeric', 'min:0.01'],
            'started_on'           => ['required', 'date'],
            'ends_on'              => ['nullable', 'date', 'after:started_on'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ];
    }
}
