<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReverseJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.journals.reverse') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'reversal_date' => ['required', 'date'],
            'reason'        => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
