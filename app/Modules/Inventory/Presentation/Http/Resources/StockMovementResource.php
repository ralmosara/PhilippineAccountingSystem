<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Infrastructure\Persistence\Eloquent\StockMovementModel
 */
final class StockMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'item_id'         => $this->item_id,
            'item'            => $this->whenLoaded('item', fn () => [
                'sku'  => $this->item->sku,
                'name' => $this->item->name,
            ]),
            'warehouse_id'    => $this->warehouse_id,
            'warehouse'       => $this->whenLoaded('warehouse', fn () => [
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'movement_type'   => $this->movement_type,
            'quantity'        => (string) $this->quantity,
            'unit_cost'       => (string) $this->unit_cost,
            'total_cost'      => (string) $this->total_cost,
            'source_doc_id'   => $this->source_doc_id,
            'source_doc_type' => $this->source_doc_type,
            'project_id'      => $this->project_id,
            'moved_at'        => $this->moved_at?->toIso8601String(),
            'moved_by'        => $this->moved_by,
            'remarks'         => $this->remarks,
        ];
    }
}
