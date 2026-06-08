<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Resources;

use App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent\FixedAssetModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FixedAssetModel
 */
final class FixedAssetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'company_id'               => $this->company_id,
            'branch_id'                => $this->branch_id,
            'cost_center_id'           => $this->cost_center_id,
            'asset_no'                 => $this->asset_no,
            'name'                     => $this->name,
            'description'              => $this->description,
            'category'                 => $this->category,
            'category_label'           => $this->categoryLabel(),
            'acquisition_date'         => $this->acquisition_date?->format('Y-m-d'),
            'acquisition_cost'         => (string) $this->acquisition_cost,
            'salvage_value'            => (string) $this->salvage_value,
            'useful_life_months'       => $this->useful_life_months,
            'depreciation_method'      => $this->depreciation_method,
            'depreciation_method_label'=> $this->depreciationMethodLabel(),
            'accumulated_depreciation' => (string) $this->accumulated_depreciation,
            'book_value'               => (string) $this->book_value,
            'asset_account_id'         => $this->asset_account_id,
            'accum_depr_account_id'    => $this->accum_depr_account_id,
            'depr_expense_account_id'  => $this->depr_expense_account_id,
            'status'                   => $this->status,
            'disposed_at'              => $this->disposed_at?->toIso8601String(),
            'disposal_proceeds'        => $this->disposal_proceeds !== null ? (string) $this->disposal_proceeds : null,
            'disposal_gain_loss'       => $this->disposal_gain_loss !== null ? (string) $this->disposal_gain_loss : null,
            'disposal_journal_entry_id'=> $this->disposal_journal_entry_id,
            'created_at'               => $this->created_at?->toIso8601String(),
            'updated_at'               => $this->updated_at?->toIso8601String(),
        ];
    }

    private function categoryLabel(): string
    {
        return match ($this->category) {
            'land'                 => 'Land',
            'building'             => 'Building',
            'equipment'            => 'Equipment',
            'vehicle'              => 'Vehicle',
            'furniture'            => 'Furniture & Fixtures',
            'it_equipment'         => 'IT Equipment',
            'leasehold_improvement'=> 'Leasehold Improvement',
            default                => ucfirst(str_replace('_', ' ', $this->category)),
        };
    }

    private function depreciationMethodLabel(): string
    {
        return match ($this->depreciation_method) {
            'straight_line'             => 'Straight-Line',
            'double_declining_balance'  => 'Double-Declining Balance',
            default                     => $this->depreciation_method,
        };
    }
}
