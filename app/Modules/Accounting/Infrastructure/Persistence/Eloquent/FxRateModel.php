<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FxRateModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.fx_rates';

    /** @var array<int, string> */
    protected $fillable = ['rate_date', 'from_ccy', 'to_ccy', 'rate', 'source', 'fetched_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate_date'  => 'date',
            'rate'       => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }
}
