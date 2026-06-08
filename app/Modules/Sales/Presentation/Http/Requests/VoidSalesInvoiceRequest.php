<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VoidSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.invoices.void') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
