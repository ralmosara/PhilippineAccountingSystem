<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateInventoryListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.forms.generate') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'year'       => ['required', 'integer', 'min:2018', 'max:2099'],
            'as_of_date' => ['nullable', 'date'],
        ];
    }
}
