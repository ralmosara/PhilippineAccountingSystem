<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PeriodReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reporting.financials.view') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ];
    }
}
