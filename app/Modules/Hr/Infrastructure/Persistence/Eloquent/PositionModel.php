<?php

declare(strict_types=1);

namespace App\Modules\Hr\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PositionModel extends Model
{
    use HasUuids;

    protected $table = 'hr.positions';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'code', 'title', 'department_id', 'description',
        'salary_grade_min', 'salary_grade_max', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'salary_grade_min' => 'decimal:2',
            'salary_grade_max' => 'decimal:2',
        ];
    }
}
