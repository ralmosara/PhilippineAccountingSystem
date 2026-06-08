<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BirFormModel extends Model
{
    use HasUuids;

    protected $table = 'tax.bir_forms';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'form_type', 'period_from', 'period_to',
        'year', 'quarter', 'month', 'data',
        'xml_path', 'pdf_path', 'dat_path',
        'generated_at', 'generated_by',
        'filed_at', 'bir_filing_ref',
        'tax_due', 'tax_paid', 'status', 'replaced_by_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_from'  => 'date',
            'period_to'    => 'date',
            'data'         => 'array',
            'generated_at' => 'datetime',
            'filed_at'     => 'datetime',
            'tax_due'      => 'decimal:2',
            'tax_paid'     => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BirFormLineModel::class, 'bir_form_id');
    }

    public function alphalistEntries(): HasMany
    {
        return $this->hasMany(AlphalistEntryModel::class, 'bir_form_id');
    }
}
