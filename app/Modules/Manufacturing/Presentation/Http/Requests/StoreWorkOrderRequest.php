<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manufacturing.work_orders.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'bom_id'                    => ['required', 'uuid'],
            'quantity_to_produce'       => ['required', 'numeric', 'min:0.0001'],
            'scheduled_start'           => ['nullable', 'date'],
            'scheduled_end'             => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            'warehouse_id'              => ['nullable', 'uuid'],
            'wip_account_id'            => ['nullable', 'uuid'],
            'finished_goods_account_id' => ['nullable', 'uuid'],
            'raw_materials_account_id'  => ['nullable', 'uuid'],
        ];
    }
}
