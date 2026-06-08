<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only pre-depreciation fields are editable:
 * name, description, and the three linked account IDs.
 * All other fields are immutable once any depreciation has been posted.
 */
final class UpdateFixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assets.register') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'                   => ['sometimes', 'required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'asset_account_id'       => ['nullable', 'uuid'],
            'accum_depr_account_id'  => ['nullable', 'uuid'],
            'depr_expense_account_id'=> ['nullable', 'uuid'],
        ];
    }
}
