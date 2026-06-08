<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class Generate13thMonthRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.runs.compute') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function year(): int
    {
        return (int) $this->input('year');
    }
}
