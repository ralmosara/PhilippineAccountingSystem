<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'code'              => ['required', 'string', 'max:32'],
            'name'              => ['required', 'string', 'max:255'],
            'billing_type'      => ['required', 'string', 'in:fixed_price,time_and_materials,retainer'],
            'contract_value'    => ['nullable', 'numeric', 'min:0'],
            'budget_hours'      => ['nullable', 'numeric', 'min:0'],
            'customer_id'       => ['nullable', 'uuid'],
            'wip_account_id'    => ['nullable', 'uuid'],
            'revenue_account_id'=> ['nullable', 'uuid'],
            'starts_on'         => ['nullable', 'date'],
            'ends_on'           => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
