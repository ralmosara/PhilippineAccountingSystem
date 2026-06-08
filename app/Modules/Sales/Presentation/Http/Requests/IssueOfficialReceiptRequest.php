<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IssueOfficialReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.or.issue') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'customer_id'         => ['required', 'uuid'],
            'sales_invoice_id'    => ['nullable', 'uuid'],
            'document_series_id'  => ['required', 'uuid'],
            'received_date'       => ['required', 'date'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'cash_account_id'     => ['required', 'uuid'],
            'ar_account_id'       => ['required', 'uuid'],
            'payment_method'      => ['required', 'in:cash,check,bank_transfer,credit_card,gcash,maya'],
            'reference_no'        => ['nullable', 'string', 'max:128'],
            'remarks'             => ['nullable', 'string', 'max:500'],
        ];
    }
}
