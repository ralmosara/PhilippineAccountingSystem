<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FiscalYearModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.fiscal_years';

    /** @var array<int, string> */
    protected $fillable = ['company_id', 'year_number', 'starts_on', 'ends_on', 'closed_at', 'closed_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on'   => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriodModel::class, 'fiscal_year_id')->orderBy('period_number');
    }
}
