<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AuthenticateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
