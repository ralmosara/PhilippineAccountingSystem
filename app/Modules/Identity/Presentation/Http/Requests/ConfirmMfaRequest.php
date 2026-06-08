<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmMfaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
