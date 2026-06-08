<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Recording stock changes maps to the same permission as posting bills/invoices
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'item_id'         => ['required', 'uuid'],
            'warehouse_id'    => ['required', 'uuid'],
            'movement_type'   => ['required', 'in:receipt,issue,transfer_in,transfer_out,adjustment,production,consumption,return'],
            'quantity'        => ['required', 'numeric', 'min:0.0001'],
            'unit_cost'       => ['nullable', 'numeric', 'min:0'],
            'source_doc_id'   => ['nullable', 'uuid'],
            'source_doc_type' => ['nullable', 'string', 'max:64'],
            'project_id'      => ['nullable', 'uuid'],
            'remarks'         => ['nullable', 'string', 'max:500'],
        ];
    }
}
