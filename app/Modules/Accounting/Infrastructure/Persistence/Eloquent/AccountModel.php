<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AccountModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.accounts';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'normal_balance',
        'parent_id', 'path', 'is_postable', 'is_active',
        'description', 'pfrs_classification',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
