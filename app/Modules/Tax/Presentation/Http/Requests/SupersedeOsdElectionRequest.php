<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SupersedeOsdElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.osd_election.supersede') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'new_regime' => ['required', 'string', 'in:itemized,osd,flat_8pct'],
            // Reason is the auditable record — BIR auditors will read this.
            // Require enough text to be a real justification, not "fix".
            'reason'     => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.min' => 'Supersede reason must be at least 20 characters — '
                .'cite the BIR amendment approval (RMC, RMO, or letter) and the affected quarter(s).',
        ];
    }
}
