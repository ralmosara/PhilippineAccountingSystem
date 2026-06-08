<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LockFiscalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.periods.lock') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
