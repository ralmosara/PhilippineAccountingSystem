<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AlphalistEntryModel extends Model
{
    use HasUuids;

    protected $table = 'tax.alphalist_entries';

    /** @var array<int, string> */
    protected $fillable = [
        'bir_form_id', 'schedule', 'tin', 'registered_name',
        'atc_code', 'nature_of_payment', 'income_payment', 'tax_withheld',
        'tax_type', 'payment_date', 'source_refs',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'income_payment'    => 'decimal:2',
            'tax_withheld'      => 'decimal:2',
            'nature_of_payment' => 'decimal:2',
            'payment_date'      => 'date',
            'source_refs'       => 'array',
        ];
    }
}
