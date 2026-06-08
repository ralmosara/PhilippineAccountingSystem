<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCompensationPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.runs.compute') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id'             => ['required', 'uuid'],
            'effective_from'          => ['required', 'date'],
            'effective_to'            => ['nullable', 'date', 'after:effective_from'],
            'basic_monthly'           => ['required', 'numeric', 'min:0'],
            'basic_daily'             => ['nullable', 'numeric', 'min:0'],
            'working_days_per_month'  => ['nullable', 'integer', 'min:1', 'max:31'],
            'hours_per_day'           => ['nullable', 'integer', 'min:1', 'max:24'],
            'hourly_rate'             => ['nullable', 'numeric', 'min:0'],
            'is_minimum_wage_earner'  => ['boolean'],
        ];
    }
}
