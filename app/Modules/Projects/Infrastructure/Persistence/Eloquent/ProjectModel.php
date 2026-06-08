<?php

declare(strict_types=1);

namespace App\Modules\Projects\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProjectModel extends Model
{
    use HasUuids;

    protected $table = 'projects.projects';

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'company_id', 'customer_id', 'code', 'name',
        'billing_type', 'status',
        'contract_value', 'budget_hours',
        'wip_account_id', 'revenue_account_id',
        'starts_on', 'ends_on', 'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'budget_hours'   => 'decimal:2',
            'starts_on'      => 'date:Y-m-d',
            'ends_on'        => 'date:Y-m-d',
            'completed_at'   => 'datetime',
        ];
    }

    public function timesheetEntries(): HasMany
    {
        return $this->hasMany(TimesheetEntryModel::class, 'project_id');
    }

    public function wipEntries(): HasMany
    {
        return $this->hasMany(WipEntryModel::class, 'project_id');
    }
}
