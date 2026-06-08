<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ComputePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.runs.compute') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'payroll_period_id' => ['required', 'uuid'],
            'period_start'      => ['required', 'date'],
            'period_end'        => ['required', 'date', 'after_or_equal:period_start'],
            'frequency'         => ['required', 'in:monthly,semimonthly,biweekly,weekly'],
            'run_type'          => ['nullable', 'in:regular,13th_month,final_pay,adjustment'],
        ];
    }
}
