<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CompensationPackageModel extends Model
{
    use HasUuids;

    protected $table = 'payroll.compensation_packages';

    /** @var array<int, string> */
    protected $fillable = [
        'employee_id', 'effective_from', 'effective_to',
        'basic_monthly', 'basic_daily', 'working_days_per_month', 'hours_per_day',
        'hourly_rate', 'is_minimum_wage_earner',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from'         => 'date',
            'effective_to'           => 'date',
            'basic_monthly'          => 'decimal:2',
            'basic_daily'            => 'decimal:2',
            'hourly_rate'            => 'decimal:4',
            'is_minimum_wage_earner' => 'boolean',
        ];
    }
}
