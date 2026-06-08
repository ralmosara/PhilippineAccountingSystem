<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AtcCodeModel extends Model
{
    use HasUuids;

    protected $table = 'tax.atc_codes';

    /** @var array<int, string> */
    protected $fillable = [
        'tax_code_id', 'code', 'description', 'rate', 'kind',
        'effective_from', 'effective_to', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate'           => 'decimal:4',
            'effective_from' => 'date',
            'effective_to'   => 'date',
            'is_active'      => 'boolean',
        ];
    }
}
