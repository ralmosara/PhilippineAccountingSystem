<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Shared request for 1604-CF / 1604-E annual returns (year only). */
final class GenerateAnnualReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.forms.generate') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2018', 'max:2099'],
        ];
    }
}
