<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateQuarterlyFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.forms.generate') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'year'    => ['required', 'integer', 'min:2018', 'max:2099'],
            'quarter' => ['required', 'integer', 'min:1', 'max:4'],
        ];
    }
}
