<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTimesheetEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.timesheets.log') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'employee_id'    => ['required', 'uuid'],
            'work_date'      => ['required', 'date'],
            'hours'          => ['required', 'numeric', 'min:0.25', 'max:24'],
            'billable_rate'  => ['nullable', 'numeric', 'min:0'],
            'description'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
