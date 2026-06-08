<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Domain\ValueObjects\ItemId;

final readonly class Item
{
    public function __construct(
        public ItemId $id,
        public string $companyId,
        public string $sku,
        public string $name,
        public ?string $categoryId,
        public string $kind,                       // stock | service | raw_material | fg | wip | asset
        public string $uomId,
        public string $costingMethod,              // moving_average | fifo | standard
        public Money $movingAvgCost,
        public Money $sellingPrice,
        public bool $isVatable,
        public bool $isInventory,
        public bool $isActive,
    ) {
    }

    public function isService(): bool
    {
        return $this->kind === 'service' || ! $this->isInventory;
    }
}
