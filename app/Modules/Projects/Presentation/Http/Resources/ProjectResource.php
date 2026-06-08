<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Projects\Infrastructure\Persistence\Eloquent\ProjectModel
 */
final class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'company_id'         => $this->company_id,
            'customer_id'        => $this->customer_id,
            'code'               => $this->code,
            'name'               => $this->name,
            'billing_type'       => $this->billing_type,
            'status'             => $this->status,
            'contract_value'     => $this->contract_value !== null ? (string) $this->contract_value : null,
            'budget_hours'       => $this->budget_hours !== null ? (string) $this->budget_hours : null,
            'wip_account_id'     => $this->wip_account_id,
            'revenue_account_id' => $this->revenue_account_id,
            'starts_on'          => $this->starts_on?->format('Y-m-d'),
            'ends_on'            => $this->ends_on?->format('Y-m-d'),
            'completed_at'       => $this->completed_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
            'timesheet_entry_count' => $this->whenLoaded(
                'timesheetEntries',
                fn () => $this->timesheetEntries->count()
            ),
        ];
    }
}
