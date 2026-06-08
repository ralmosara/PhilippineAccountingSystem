<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OsdElectionModel extends Model
{
    use HasUuids;

    protected $table = 'tax.osd_elections';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'fiscal_year', 'taxpayer_type', 'regime',
        'declared_in_form_type', 'declared_in_quarter', 'declared_in_bir_form_id',
        'locked_at', 'locked_by',
        'replaces_id', 'superseded_at', 'supersede_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiscal_year'        => 'integer',
            'declared_in_quarter' => 'integer',
            'locked_at'           => 'datetime',
            'superseded_at'       => 'datetime',
        ];
    }
}
