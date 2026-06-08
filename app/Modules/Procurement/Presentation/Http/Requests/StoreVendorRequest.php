<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.bills.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'registered_name'           => ['required', 'string', 'max:255'],
            'tin'                       => ['nullable', 'string', 'max:32',
                Rule::unique('procurement.vendors', 'tin')
                    ->where('company_id', $this->user()->company_id)],
            'is_vat_registered'         => ['nullable', 'boolean'],
            'is_government_supplier'    => ['nullable', 'boolean'],
            'is_top_withholding_agent'  => ['nullable', 'boolean'],
            'default_atc_code'          => ['nullable', 'string', 'max:16',
                Rule::exists('tax.atc_codes', 'code')->where('is_active', true)],
            'default_withholding_rate'  => ['nullable', 'numeric', 'min:0', 'max:1'],
            'payment_terms_days'        => ['nullable', 'integer', 'min:0', 'max:365'],
            'email'                     => ['nullable', 'email', 'max:255'],
        ];
    }
}
