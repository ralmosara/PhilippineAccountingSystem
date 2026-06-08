<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Stock adjustments are sensitive — same as journal posting
        return $this->user()?->can('accounting.journals.post') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'item_id'      => ['required', 'uuid'],
            'warehouse_id' => ['required', 'uuid'],
            'quantity'     => ['required', 'numeric', 'min:0.0001'],
            'is_positive'  => ['required', 'boolean'],
            'unit_cost'    => ['required', 'numeric', 'min:0'],
            'reason'       => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
