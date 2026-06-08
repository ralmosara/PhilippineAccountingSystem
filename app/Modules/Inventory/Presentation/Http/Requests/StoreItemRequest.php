<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Inventory items configurable by anyone who can manage sales — sufficient
        // for the demo; tighten as needed with a dedicated `inventory.items.manage`.
        return $this->user()?->can('sales.invoices.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'sku'             => ['nullable', 'string', 'max:64',
                Rule::unique('inventory.items', 'sku')->where('company_id', $this->user()->company_id)],
            'name'            => ['required', 'string', 'max:255'],
            'category_id'     => ['nullable', 'uuid'],
            'kind'            => ['nullable', 'in:stock,service,raw_material,fg,wip,asset'],
            'uom_id'          => ['required', 'uuid'],
            'costing_method'  => ['nullable', 'in:moving_average,fifo,standard'],
            'selling_price'   => ['nullable', 'numeric', 'min:0'],
            'initial_cost'    => ['nullable', 'numeric', 'min:0'],
            'is_vatable'      => ['nullable', 'boolean'],
            'is_inventory'    => ['nullable', 'boolean'],
        ];
    }
}
