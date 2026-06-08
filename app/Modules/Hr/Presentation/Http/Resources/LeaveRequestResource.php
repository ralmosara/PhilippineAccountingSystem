<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Infrastructure\Persistence\Eloquent\LeaveRequestModel
 */
final class LeaveRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'company_id'       => $this->company_id,
            'employee_id'      => $this->employee_id,
            'leave_type'       => $this->leave_type,
            'start_date'       => $this->start_date?->format('Y-m-d'),
            'end_date'         => $this->end_date?->format('Y-m-d'),
            'days_requested'   => $this->days_requested,
            'reason'           => $this->reason,
            'status'           => $this->status,
            'approved_by'      => $this->approved_by,
            'approved_at'      => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
