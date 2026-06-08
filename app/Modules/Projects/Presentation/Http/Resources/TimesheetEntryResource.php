<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Projects\Infrastructure\Persistence\Eloquent\TimesheetEntryModel
 */
final class TimesheetEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'project_id'      => $this->project_id,
            'employee_id'     => $this->employee_id,
            'work_date'       => $this->work_date?->format('Y-m-d'),
            'hours'           => (string) $this->hours,
            'billable_rate'   => (string) $this->billable_rate,
            'billable_amount' => (string) $this->billable_amount,
            'description'     => $this->description,
            'is_billed'       => (bool) $this->is_billed,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
