<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IssueSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.invoices.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'customer_id'             => ['required', 'uuid'],
            'document_series_id'      => ['required', 'uuid'],
            'invoice_date'            => ['required', 'date'],
            'due_date'                => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'doc_kind'                => ['nullable', 'in:cash,charge'],
            'currency'                => ['nullable', 'string', 'size:3'],
            'ar_account_id'           => ['required', 'uuid'],
            'vat_payable_account_id'  => ['required', 'uuid'],

            'lines'                       => ['required', 'array', 'min:1'],
            'lines.*.description'         => ['required', 'string', 'max:500'],
            'lines.*.quantity'            => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price'          => ['required', 'numeric', 'min:0'],
            'lines.*.tax_code_id'         => ['nullable', 'uuid'],
            'lines.*.tax_kind'            => ['nullable', 'in:vat_output,vat_zero,vat_exempt'],
            'lines.*.item_id'             => ['nullable', 'uuid'],
            'lines.*.revenue_account_id'  => ['required', 'uuid'],
            'lines.*.project_id'          => ['nullable', 'uuid'],
            'lines.*.discount_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
