<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('identity.users.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'email'     => ['required', 'email', 'max:255', Rule::unique('identity.users', 'email')],
            'full_name' => ['required', 'string', 'max:255'],
            'password'  => ['required', 'string', 'min:12'],
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['string', Rule::exists('identity.roles', 'name')],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['uuid'],
        ];
    }
}
