<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class UnitOfMeasureModel extends Model
{
    use HasUuids;

    protected $table = 'inventory.units_of_measure';

    /** @var array<int, string> */
    protected $fillable = ['code', 'name', 'category', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
