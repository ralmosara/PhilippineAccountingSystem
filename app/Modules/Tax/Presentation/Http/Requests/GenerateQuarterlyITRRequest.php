<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared form-request for both 1701Q (individual) and 1702Q (corporate)
 * quarterly ITRs. The 1701Q-specific fields (elect_flat_8pct, personal_exemption)
 * are ignored by the 1702Q controller.
 */
final class GenerateQuarterlyITRRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.forms.generate') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'year'                  => ['required', 'integer', 'min:2018', 'max:2099'],
            // Q4 not allowed — folded into the annual ITR. Action also enforces this.
            'quarter'               => ['required', 'integer', 'min:1', 'max:3'],
            'creditable_wt'         => ['nullable', 'numeric', 'min:0'],
            // 1701Q-only
            'elect_flat_8pct'       => ['nullable', 'boolean'],
            'personal_exemption'    => ['nullable', 'numeric', 'min:0'],
            // OSD — must match Q1's election in Q2/Q3 (action enforces lock-in)
            'use_osd'               => ['nullable', 'boolean'],
        ];
    }
}
