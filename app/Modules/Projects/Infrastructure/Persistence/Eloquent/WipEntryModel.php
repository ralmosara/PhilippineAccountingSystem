<?php

declare(strict_types=1);

namespace App\Modules\Projects\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WipEntryModel extends Model
{
    use HasUuids;

    protected $table = 'projects.wip_entries';

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'project_id', 'journal_entry_id',
        'period_from', 'period_to',
        'total_hours', 'total_cost', 'total_billed', 'recognized_revenue',
        'status', 'posted_at', 'posted_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_from'        => 'date:Y-m-d',
            'period_to'          => 'date:Y-m-d',
            'total_hours'        => 'decimal:2',
            'total_cost'         => 'decimal:2',
            'total_billed'       => 'decimal:2',
            'recognized_revenue' => 'decimal:2',
            'posted_at'          => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectModel::class, 'project_id');
    }
}
