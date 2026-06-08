<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CostCenterModel extends Model
{
    use HasUuids;

    protected $table = 'accounting.cost_centers';

    /** @var array<int, string> */
    protected $fillable = ['company_id', 'code', 'name', 'parent_id', 'path', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
