<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApprovePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.runs.approve') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'accounts'                                     => ['required', 'array'],
            'accounts.salaries_expense_account_id'         => ['required', 'uuid'],
            'accounts.employer_contributions_account_id'   => ['required', 'uuid'],
            'accounts.sss_payable_account_id'              => ['required', 'uuid'],
            'accounts.phic_payable_account_id'             => ['required', 'uuid'],
            'accounts.hdmf_payable_account_id'             => ['required', 'uuid'],
            'accounts.wt_compensation_payable_account_id'  => ['required', 'uuid'],
            'accounts.salaries_payable_account_id'         => ['required', 'uuid'],
            'accounts.jv_document_series_id'               => ['required', 'uuid'],
        ];
    }
}
