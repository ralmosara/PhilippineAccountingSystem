<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Contracts;

use App\Modules\Inventory\Domain\Entities\Warehouse;
use App\Modules\Inventory\Domain\ValueObjects\WarehouseId;

interface WarehouseRepositoryContract
{
    public function findById(WarehouseId $id): ?Warehouse;

    public function findDefaultFor(string $companyId): ?Warehouse;

    public function save(Warehouse $warehouse): void;
}
