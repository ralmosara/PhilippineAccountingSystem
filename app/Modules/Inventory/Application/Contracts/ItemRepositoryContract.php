<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Contracts;

use App\Modules\Inventory\Domain\Entities\Item;
use App\Modules\Inventory\Domain\ValueObjects\ItemId;

interface ItemRepositoryContract
{
    public function findById(ItemId $id): ?Item;

    public function save(Item $item): void;

    public function updateMovingAvgCost(ItemId $id, string $newCost): void;

    public function nextSku(string $companyId): string;
}
