<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Requests;

use App\Modules\FixedAssets\Domain\ValueObjects\AssetCategory;
use App\Modules\FixedAssets\Domain\ValueObjects\DepreciationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assets.register') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $categories = array_column(AssetCategory::cases(), 'value');
        $methods    = array_column(DepreciationMethod::cases(), 'value');

        return [
            'name'                   => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'category'               => ['required', Rule::in($categories)],
            'acquisition_date'       => ['required', 'date'],
            'acquisition_cost'       => ['required', 'numeric', 'min:0'],
            'salvage_value'          => ['nullable', 'numeric', 'min:0'],
            'useful_life_months'     => ['required', 'integer', 'min:0'],
            'depreciation_method'    => ['required', Rule::in($methods)],
            'branch_id'              => ['nullable', 'uuid'],
            'cost_center_id'         => ['nullable', 'uuid'],
            'asset_account_id'       => ['nullable', 'uuid'],
            'accum_depr_account_id'  => ['nullable', 'uuid'],
            'depr_expense_account_id'=> ['nullable', 'uuid'],
        ];
    }
}
