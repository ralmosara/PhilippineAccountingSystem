<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecognizeWipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.wip.recognize') ?? false;
    }

    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'period_from'        => ['required', 'date'],
            'period_to'          => ['required', 'date', 'after_or_equal:period_from'],
            'wip_account_id'     => ['required', 'uuid'],
            'revenue_account_id' => ['required', 'uuid'],
        ];
    }
}
