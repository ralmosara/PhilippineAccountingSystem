<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FiscalPeriodModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.fiscal_periods';

    /** @var array<int, string> */
    protected $fillable = [
        'fiscal_year_id', 'period_number', 'label', 'starts_on', 'ends_on',
        'locked_at', 'locked_by', 'lock_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on'   => 'date',
            'locked_at' => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYearModel::class, 'fiscal_year_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
