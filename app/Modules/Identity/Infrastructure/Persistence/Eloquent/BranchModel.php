<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class BranchModel extends Model
{
    use HasUuids;

    protected $table = 'identity.branches';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'bir_branch_code',
        'address',
        'telephone',
        'is_head_office',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_head_office' => 'boolean',
            'is_active'      => 'boolean',
        ];
    }
}
