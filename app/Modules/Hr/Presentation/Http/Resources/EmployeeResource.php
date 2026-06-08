<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Infrastructure\Persistence\Eloquent\EmployeeModel
 */
final class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // PII (TIN/SSS/PhilHealth/Pag-IBIG) only exposed to roles with explicit permission
        $canSeePii = $request->user()?->can('hr.employees.manage') ?? false;

        return [
            'id'                 => $this->id,
            'employee_no'        => $this->employee_no,
            'first_name'         => $this->first_name,
            'middle_name'        => $this->middle_name,
            'last_name'          => $this->last_name,
            'full_name'          => trim("{$this->first_name} {$this->middle_name} {$this->last_name}"),
            'email'              => $this->email,
            'hired_on'           => $this->hired_on?->format('Y-m-d'),
            'separated_on'       => $this->separated_on?->format('Y-m-d'),
            'employment_status'  => $this->employment_status,
            'department_id'      => $this->department_id,
            'position_id'        => $this->position_id,
            'is_active'          => (bool) $this->is_active,
            // PII fields — masked for read-only roles
            'tin'                => $canSeePii ? $this->tin() : $this->maskPii($this->tin()),
            'sss_no'             => $canSeePii ? $this->sssNo() : $this->maskPii($this->sssNo()),
            'philhealth_no'      => $canSeePii ? $this->philhealthNo() : $this->maskPii($this->philhealthNo()),
            'pagibig_no'         => $canSeePii ? $this->pagibigNo() : $this->maskPii($this->pagibigNo()),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }

    private function maskPii(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
