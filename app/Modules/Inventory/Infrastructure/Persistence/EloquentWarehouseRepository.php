<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Application\Contracts\WarehouseRepositoryContract;
use App\Modules\Inventory\Domain\Entities\Warehouse;
use App\Modules\Inventory\Domain\ValueObjects\WarehouseId;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\WarehouseModel;

final class EloquentWarehouseRepository implements WarehouseRepositoryContract
{
    public function findById(WarehouseId $id): ?Warehouse
    {
        $model = WarehouseModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function findDefaultFor(string $companyId): ?Warehouse
    {
        $model = WarehouseModel::query()
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Warehouse $w): void
    {
        WarehouseModel::query()->updateOrInsert(
            ['id' => $w->id->value],
            [
                'company_id' => $w->companyId,
                'branch_id'  => $w->branchId,
                'code'       => $w->code,
                'name'       => $w->name,
                'address'    => $w->address,
                'is_default' => $w->isDefault,
                'is_active'  => $w->isActive,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function toDomain(WarehouseModel $m): Warehouse
    {
        return new Warehouse(
            id:        new WarehouseId($m->id),
            companyId: $m->company_id,
            branchId:  $m->branch_id,
            code:      $m->code,
            name:      $m->name,
            address:   $m->address,
            isDefault: (bool) $m->is_default,
            isActive:  (bool) $m->is_active,
        );
    }
}
