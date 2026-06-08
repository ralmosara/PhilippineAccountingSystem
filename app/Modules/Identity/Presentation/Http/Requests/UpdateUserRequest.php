<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('identity.users.update') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'email' => ['sometimes', 'email', 'max:255',
                Rule::unique('identity.users', 'email')->ignore($userId)],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('identity.roles', 'name')],
        ];
    }
}
