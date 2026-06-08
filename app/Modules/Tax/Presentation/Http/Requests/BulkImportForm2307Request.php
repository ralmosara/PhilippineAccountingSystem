<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BulkImportForm2307Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.form_2307_received.write') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            // Accept either an uploaded file OR a raw CSV string in JSON body.
            // (Operators often paste the customer statement directly.)
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],   // ≤ 5MB
            'csv'  => ['nullable', 'string', 'max:5242880'],                // ≤ 5MB raw
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->hasFile('file') && ! filled($this->input('csv'))) {
                $v->errors()->add('file', 'Either an uploaded CSV file or a "csv" string payload is required.');
            }
        });
    }

    public function csvContent(): string
    {
        if ($this->hasFile('file')) {
            return (string) file_get_contents($this->file('file')->getRealPath());
        }
        return (string) $this->input('csv');
    }
}
