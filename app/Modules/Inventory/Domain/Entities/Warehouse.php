<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

use App\Modules\Inventory\Domain\ValueObjects\WarehouseId;

final readonly class Warehouse
{
    public function __construct(
        public WarehouseId $id,
        public string $companyId,
        public ?string $branchId,
        public string $code,
        public string $name,
        public ?string $address,
        public bool $isDefault,
        public bool $isActive,
    ) {
    }
}
