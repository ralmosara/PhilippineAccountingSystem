<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PostVendorBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.bills.post') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'vendor_id'                       => ['required', 'uuid'],
            'purchase_order_id'               => ['nullable', 'uuid'],
            'vendor_invoice_no'               => ['required', 'string', 'max:64'],
            'vendor_invoice_date'             => ['required', 'date'],
            'bill_date'                       => ['required', 'date'],
            'due_date'                        => ['nullable', 'date'],
            'currency'                        => ['nullable', 'string', 'size:3'],
            'atc_code_override'               => ['nullable', 'string', 'max:16'],

            'ap_account_id'                   => ['required', 'uuid'],
            'vat_input_account_id'            => ['required', 'uuid'],
            'withholding_payable_account_id'  => ['required', 'uuid'],
            'jv_document_series_id'           => ['required', 'uuid'],

            'lines'                           => ['required', 'array', 'min:1'],
            'lines.*.description'             => ['required', 'string', 'max:500'],
            'lines.*.quantity'                => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price'              => ['required', 'numeric', 'min:0'],
            'lines.*.vat_amount'              => ['nullable', 'numeric', 'min:0'],
            'lines.*.expense_account_id'      => ['required', 'uuid'],
            'lines.*.tax_code_id'             => ['nullable', 'uuid'],
            'lines.*.item_id'                 => ['nullable', 'uuid'],
            'lines.*.project_id'              => ['nullable', 'uuid'],
            'lines.*.purchase_order_line_id'  => ['nullable', 'uuid'],
        ];
    }
}
