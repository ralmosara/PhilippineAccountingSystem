<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Application\Contracts\ItemRepositoryContract;
use App\Modules\Inventory\Domain\Entities\Item;
use App\Modules\Inventory\Domain\ValueObjects\ItemId;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\ItemModel;

final class EloquentItemRepository implements ItemRepositoryContract
{
    public function findById(ItemId $id): ?Item
    {
        $model = ItemModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(Item $item): void
    {
        ItemModel::query()->updateOrInsert(
            ['id' => $item->id->value],
            [
                'company_id'      => $item->companyId,
                'category_id'     => $item->categoryId,
                'sku'             => $item->sku,
                'name'            => $item->name,
                'kind'            => $item->kind,
                'uom_id'          => $item->uomId,
                'costing_method'  => $item->costingMethod,
                'moving_avg_cost' => $item->movingAvgCost->amount,
                'selling_price'   => $item->sellingPrice->amount,
                'is_vatable'      => $item->isVatable,
                'is_inventory'    => $item->isInventory,
                'is_active'       => $item->isActive,
                'updated_at'      => now(),
                'created_at'      => now(),
            ],
        );
    }

    public function updateMovingAvgCost(ItemId $id, string $newCost): void
    {
        ItemModel::query()
            ->where('id', $id->value)
            ->update(['moving_avg_cost' => $newCost, 'updated_at' => now()]);
    }

    public function nextSku(string $companyId): string
    {
        $count = ItemModel::query()->where('company_id', $companyId)->count();
        return 'ITEM-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    private function toDomain(ItemModel $m): Item
    {
        return new Item(
            id:               new ItemId($m->id),
            companyId:        $m->company_id,
            sku:              $m->sku,
            name:             $m->name,
            categoryId:       $m->category_id,
            kind:             $m->kind,
            uomId:            $m->uom_id,
            costingMethod:    $m->costing_method,
            movingAvgCost:    Money::php((string) $m->moving_avg_cost),
            sellingPrice:     Money::php((string) $m->selling_price),
            isVatable:        (bool) $m->is_vatable,
            isInventory:      (bool) $m->is_inventory,
            isActive:         (bool) $m->is_active,
        );
    }
}
