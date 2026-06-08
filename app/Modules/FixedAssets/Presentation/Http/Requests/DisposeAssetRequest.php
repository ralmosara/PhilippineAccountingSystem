<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DisposeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assets.register') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proceeds'           => ['required', 'numeric', 'min:0'],
            'disposal_date'      => ['required', 'date'],
            'cash_account_id'    => ['required', 'uuid'],
            'gain_loss_account_id' => ['required', 'uuid'],
        ];
    }
}
