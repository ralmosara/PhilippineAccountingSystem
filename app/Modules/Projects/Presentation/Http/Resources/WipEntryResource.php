<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Projects\Infrastructure\Persistence\Eloquent\WipEntryModel
 */
final class WipEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'project_id'         => $this->project_id,
            'journal_entry_id'   => $this->journal_entry_id,
            'period_from'        => $this->period_from?->format('Y-m-d'),
            'period_to'          => $this->period_to?->format('Y-m-d'),
            'total_hours'        => (string) $this->total_hours,
            'total_cost'         => (string) $this->total_cost,
            'total_billed'       => (string) $this->total_billed,
            'recognized_revenue' => (string) $this->recognized_revenue,
            'status'             => $this->status,
            'posted_at'          => $this->posted_at?->toIso8601String(),
            'posted_by'          => $this->posted_by,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
