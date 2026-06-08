<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteProductionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manufacturing.work_orders.complete') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'quantity_produced' => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}
