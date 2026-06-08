<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.invoices.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'registered_name'    => ['required', 'string', 'max:255'],
            'tin'                => ['nullable', 'string', 'max:32',
                Rule::unique('sales.customers', 'tin')
                    ->where('company_id', $this->user()->company_id)],
            'is_vat_registered'  => ['nullable', 'boolean'],
            'is_government'      => ['nullable', 'boolean'],
            'is_senior_citizen'  => ['nullable', 'boolean'],
            'is_pwd'             => ['nullable', 'boolean'],
            'email'              => ['nullable', 'email', 'max:255'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
