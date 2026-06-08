<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateForm2316Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.forms.generate') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid'],
            'year'        => ['required', 'integer', 'min:2018', 'max:2099'],
        ];
    }
}
