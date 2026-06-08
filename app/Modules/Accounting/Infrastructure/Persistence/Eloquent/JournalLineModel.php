<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class JournalLineModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.journal_lines';

    /** @var array<int, string> */
    protected $fillable = [
        'journal_entry_id', 'line_no', 'account_id',
        'currency', 'debit', 'credit', 'fx_rate', 'php_amount',
        'tax_code_id', 'cost_center_id', 'project_id', 'memo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'debit'      => 'decimal:4',
            'credit'     => 'decimal:4',
            'fx_rate'    => 'decimal:8',
            'php_amount' => 'decimal:2',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntryModel::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountModel::class, 'account_id');
    }
}
