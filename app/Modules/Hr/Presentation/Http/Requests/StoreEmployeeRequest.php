<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.employees.manage') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'first_name'        => ['required', 'string', 'max:255'],
            'last_name'         => ['required', 'string', 'max:255'],
            'middle_name'       => ['nullable', 'string', 'max:255'],
            'tin'               => ['nullable', 'string', 'max:32'],
            'sss_no'            => ['nullable', 'string', 'max:32'],
            'philhealth_no'     => ['nullable', 'string', 'max:32'],
            'pagibig_no'        => ['nullable', 'string', 'max:32'],
            'hired_on'          => ['required', 'date'],
            'employment_status' => ['nullable', 'in:probationary,regular,contract,project,consultant'],
            'department_id'     => ['nullable', 'uuid'],
            'position_id'       => ['nullable', 'uuid'],
            'email'             => ['nullable', 'email', 'max:255'],
        ];
    }
}
