<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ComputeMonthlyDepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assets.depreciation.run') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year'  => ['required', 'integer', 'min:2000'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
