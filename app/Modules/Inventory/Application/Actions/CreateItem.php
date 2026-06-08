<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Inventory\Application\Contracts\ItemRepositoryContract;
use App\Modules\Inventory\Domain\Entities\Item;
use App\Modules\Inventory\Domain\ValueObjects\ItemId;
use Illuminate\Support\Facades\DB;

final readonly class CreateItem
{
    public function __construct(
        private ItemRepositoryContract $items,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @param  array{
     *     sku?: string|null,
     *     name: string,
     *     category_id?: string|null,
     *     kind?: string,
     *     uom_id: string,
     *     costing_method?: string,
     *     selling_price?: string|float|int,
     *     is_vatable?: bool,
     *     is_inventory?: bool,
     *     initial_cost?: string|float|int|null,
     * }  $data
     */
    public function execute(string $companyId, array $data, string $actorId): Item
    {
        return DB::transaction(function () use ($companyId, $data, $actorId) {
            $item = new Item(
                id:               ItemId::generate(),
                companyId:        $companyId,
                sku:              $data['sku'] ?? $this->items->nextSku($companyId),
                name:             $data['name'],
                categoryId:       $data['category_id'] ?? null,
                kind:             $data['kind'] ?? 'stock',
                uomId:            $data['uom_id'],
                costingMethod:    $data['costing_method'] ?? 'moving_average',
                movingAvgCost:    Money::php((string) ($data['initial_cost'] ?? '0')),
                sellingPrice:     Money::php((string) ($data['selling_price'] ?? '0')),
                isVatable:        (bool) ($data['is_vatable'] ?? true),
                isInventory:      (bool) ($data['is_inventory'] ?? true),
                isActive:         true,
            );

            $this->items->save($item);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'item.created',
                aggregate:   'Item',
                aggregateId: $item->id->value,
                payload: [
                    'sku'             => $item->sku,
                    'name'            => $item->name,
                    'kind'            => $item->kind,
                    'costing_method'  => $item->costingMethod,
                ],
            );

            return $item;
        });
    }
}
