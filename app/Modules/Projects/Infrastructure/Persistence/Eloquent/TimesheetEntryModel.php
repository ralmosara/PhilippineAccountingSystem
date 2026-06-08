<?php

declare(strict_types=1);

namespace App\Modules\Projects\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TimesheetEntryModel extends Model
{
    use HasUuids;

    protected $table = 'projects.timesheet_entries';

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'project_id', 'employee_id',
        'work_date', 'hours',
        'billable_rate', 'billable_amount',
        'description', 'is_billed',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date'       => 'date:Y-m-d',
            'hours'           => 'decimal:2',
            'billable_rate'   => 'decimal:2',
            'billable_amount' => 'decimal:2',
            'is_billed'       => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectModel::class, 'project_id');
    }
}
