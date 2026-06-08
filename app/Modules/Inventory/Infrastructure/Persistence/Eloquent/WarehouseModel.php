<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class WarehouseModel extends Model
{
    use HasUuids;

    protected $table = 'inventory.warehouses';

    /** @var array<int, string> */
    protected $fillable = ['company_id', 'branch_id', 'code', 'name', 'address', 'is_default', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }
}
