<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateAnnualITRRequest extends FormRequest
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
            'prior_excess_credits'  => ['nullable', 'numeric', 'min:0'],
            'creditable_wt'         => ['nullable', 'numeric', 'min:0'],
            'quarterly_payments'    => ['nullable', 'numeric', 'min:0'],
            // 1701-only flags (ignored by 1702RT controller)
            'elect_flat_8pct'       => ['nullable', 'boolean'],
            'personal_exemption'    => ['nullable', 'numeric', 'min:0'],
            // OSD election (RR 2-2010) — applies to both 1701 and 1702-RT.
            // Must be declared on the FIRST quarterly return; the UI should
            // grey this out if a Q-return for the year was already filed with
            // itemized deductions. Combining with elect_flat_8pct is rejected
            // by the action (RR 8-2018 § 3).
            'use_osd'               => ['nullable', 'boolean'],
        ];
    }
}
