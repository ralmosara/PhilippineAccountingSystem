<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RejectLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.leave.approve') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
