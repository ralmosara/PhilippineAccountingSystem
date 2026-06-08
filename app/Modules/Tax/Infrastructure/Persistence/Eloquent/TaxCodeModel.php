<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TaxCodeModel extends Model
{
    use HasUuids;

    protected $table = 'tax.tax_codes';

    /** @var array<int, string> */
    protected $fillable = [
        'code', 'name', 'rate', 'kind', 'description',
        'effective_from', 'effective_to', 'replaced_by_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate'           => 'decimal:4',
            'effective_from' => 'date',
            'effective_to'   => 'date',
        ];
    }
}
