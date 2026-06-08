<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Resources;

use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\CompensationPackageModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompensationPackageModel */
final class CompensationPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'employee_id'             => $this->employee_id,
            'effective_from'          => $this->effective_from?->toDateString(),
            'effective_to'            => $this->effective_to?->toDateString(),
            'basic_monthly'           => $this->basic_monthly,
            'basic_daily'             => $this->basic_daily,
            'working_days_per_month'  => $this->working_days_per_month,
            'hours_per_day'           => $this->hours_per_day,
            'hourly_rate'             => $this->hourly_rate,
            'is_minimum_wage_earner'  => (bool) $this->is_minimum_wage_earner,
            'created_at'              => $this->created_at?->toIso8601String(),
        ];
    }
}
