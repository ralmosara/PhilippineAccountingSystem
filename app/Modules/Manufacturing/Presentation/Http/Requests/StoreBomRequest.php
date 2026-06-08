<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manufacturing.boms.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'item_id'                    => ['required', 'uuid'],
            'item_name'                  => ['required', 'string', 'max:255'],
            'code'                       => ['required', 'string', 'max:32'],
            'name'                       => ['required', 'string', 'max:255'],
            'version'                    => ['nullable', 'string', 'max:16'],
            'standard_batch_size'        => ['required', 'numeric', 'min:0.0001'],
            'labor_cost_per_batch'       => ['required', 'numeric', 'min:0'],
            'overhead_cost_per_batch'    => ['required', 'numeric', 'min:0'],
            'notes'                      => ['nullable', 'string', 'max:5000'],

            'lines'                          => ['required', 'array', 'min:1'],
            'lines.*.component_item_id'      => ['required', 'uuid'],
            'lines.*.component_name'         => ['required', 'string', 'max:255'],
            'lines.*.quantity_per_batch'     => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_of_measure'        => ['required', 'string', 'max:16'],
            'lines.*.notes'                  => ['nullable', 'string', 'max:500'],
        ];
    }
}
