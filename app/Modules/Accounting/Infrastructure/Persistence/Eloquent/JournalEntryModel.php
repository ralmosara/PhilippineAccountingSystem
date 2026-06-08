<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class JournalEntryModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.journal_entries';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'fiscal_period_id', 'document_series_id',
        'sequence_no', 'doc_no', 'entry_date', 'memo',
        'source', 'source_doc_id', 'source_doc_type',
        'posted_at', 'posted_by', 'reversed_by',
        'created_by', 'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at'  => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLineModel::class, 'journal_entry_id')
                    ->orderBy('line_no');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriodModel::class, 'fiscal_period_id');
    }

    public function documentSeries(): BelongsTo
    {
        return $this->belongsTo(DocumentSeriesModel::class, 'document_series_id');
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by');
    }
}
