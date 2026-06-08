<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Infrastructure\Persistence\Eloquent\WarehouseModel
 */
final class WarehouseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'code'       => $this->code,
            'name'       => $this->name,
            'branch_id'  => $this->branch_id,
            'address'    => $this->address,
            'is_default' => (bool) $this->is_default,
            'is_active'  => (bool) $this->is_active,
        ];
    }
}
