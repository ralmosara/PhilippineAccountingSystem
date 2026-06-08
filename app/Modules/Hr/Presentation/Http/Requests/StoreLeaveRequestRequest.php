<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user can submit a leave request on behalf of themselves
        return true;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid'],
            'leave_type'  => ['required', 'in:sick,vacation,emergency,maternity,paternity,solo_parent,bereavement'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'reason'      => ['nullable', 'string', 'max:2000'],
        ];
    }
}
