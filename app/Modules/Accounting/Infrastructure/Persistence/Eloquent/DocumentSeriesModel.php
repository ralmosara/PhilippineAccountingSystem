<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class DocumentSeriesModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.document_series';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'branch_id', 'document_type', 'prefix',
        'next_sequence', 'series_start', 'series_end',
        'bir_atp_no', 'atp_date', 'activated_at', 'exhausted_at', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'next_sequence' => 'integer',
            'series_start'  => 'integer',
            'series_end'    => 'integer',
            'atp_date'      => 'date',
            'activated_at'  => 'datetime',
            'exhausted_at'  => 'datetime',
            'is_active'     => 'boolean',
        ];
    }
}
