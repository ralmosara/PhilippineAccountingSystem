<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Infrastructure\Persistence\Eloquent\ItemModel
 */
final class ItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'sku'             => $this->sku,
            'name'            => $this->name,
            'description'     => $this->description,
            'kind'            => $this->kind,
            'category_id'     => $this->category_id,
            'category'        => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
            ]),
            'uom_id'          => $this->uom_id,
            'uom'             => $this->whenLoaded('uom', fn () => [
                'code' => $this->uom->code,
                'name' => $this->uom->name,
            ]),
            'costing_method'  => $this->costing_method,
            'moving_avg_cost' => (string) $this->moving_avg_cost,
            'selling_price'   => (string) $this->selling_price,
            'is_vatable'      => (bool) $this->is_vatable,
            'is_inventory'    => (bool) $this->is_inventory,
            'is_active'       => (bool) $this->is_active,
        ];
    }
}
