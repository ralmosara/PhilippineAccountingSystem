<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.leave.approve') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [];
    }
}
