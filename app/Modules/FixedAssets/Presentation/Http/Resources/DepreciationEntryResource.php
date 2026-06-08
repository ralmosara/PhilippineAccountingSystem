<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Resources;

use App\Modules\FixedAssets\Infrastructure\Persistence\Eloquent\DepreciationEntryModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DepreciationEntryModel
 */
final class DepreciationEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'fixed_asset_id'      => $this->fixed_asset_id,
            'period_year'         => $this->period_year,
            'period_month'        => $this->period_month,
            'period_label'        => sprintf('%04d-%02d', $this->period_year, $this->period_month),
            'depreciation_amount' => (string) $this->depreciation_amount,
            'accumulated_after'   => (string) $this->accumulated_after,
            'book_value_after'    => (string) $this->book_value_after,
            'journal_entry_id'    => $this->journal_entry_id,
            'posted_at'           => $this->posted_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
