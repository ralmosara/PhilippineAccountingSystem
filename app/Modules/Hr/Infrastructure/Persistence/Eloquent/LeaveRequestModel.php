<?php

declare(strict_types=1);

namespace App\Modules\Hr\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class LeaveRequestModel extends Model
{
    use HasUuids;

    protected $table = 'hr.leave_requests';

    /** @var array<int, string> */
    protected $fillable = [
        'id',
        'company_id',
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days_requested',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_at',
        'updated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date'    => 'date',
            'end_date'      => 'date',
            'approved_at'   => 'datetime',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
            'days_requested' => 'decimal:2',
        ];
    }
}
