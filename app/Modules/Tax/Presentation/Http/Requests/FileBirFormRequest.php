<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FileBirFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.forms.file') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'bir_filing_ref' => ['required', 'string', 'max:128'],
            'channel'        => ['required', 'in:ebirforms_offline,ebirforms_online,efps,manual'],
        ];
    }
}
