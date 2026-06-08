<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ComputeFinalPayRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.runs.compute') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'employee_id'       => ['required', 'uuid'],
            'separation_date'   => ['required', 'date'],
            'separation_reason' => [
                'required',
                'in:resigned,terminated,redundancy,retrenchment,closure,illness,other',
            ],
        ];
    }
}
