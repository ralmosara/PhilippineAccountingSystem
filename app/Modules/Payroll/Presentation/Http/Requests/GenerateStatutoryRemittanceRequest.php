<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateStatutoryRemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.statutory.file') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
        ];
    }
}
