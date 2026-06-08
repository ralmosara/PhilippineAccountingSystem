<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ReportRunModel extends Model
{
    use HasUuids;

    protected $table = 'reporting.report_runs';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'report_type', 'period_from', 'period_to',
        'as_of_date', 'payload', 'pdf_path', 'csv_path',
        'generated_at', 'generated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_from'  => 'date',
            'period_to'    => 'date',
            'as_of_date'   => 'date',
            'payload'      => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
