<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.journals.create') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'document_series_id' => ['required', 'uuid'],
            'entry_date'         => ['required', 'date'],
            'memo'               => ['nullable', 'string', 'max:1000'],
            'source'             => ['nullable', 'in:manual,sales,purchase,payroll,cash_receipt,cash_disbursement,recurring'],
            'source_doc_id'      => ['nullable', 'uuid'],
            'source_doc_type'    => ['nullable', 'string', 'max:64'],

            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['required', 'uuid'],
            'lines.*.debit'          => ['required_without:lines.*.credit', 'numeric', 'min:0'],
            'lines.*.credit'         => ['required_without:lines.*.debit', 'numeric', 'min:0'],
            'lines.*.fx_rate'        => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_code_id'    => ['nullable', 'uuid'],
            'lines.*.cost_center_id' => ['nullable', 'uuid'],
            'lines.*.project_id'     => ['nullable', 'uuid'],
            'lines.*.memo'           => ['nullable', 'string', 'max:255'],
        ];
    }
}
