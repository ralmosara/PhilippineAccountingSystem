<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Accounting\Infrastructure\Persistence\Eloquent\AccountModel
 */
final class AccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'code'                => $this->code,
            'name'                => $this->name,
            'type'                => $this->type,
            'normal_balance'      => $this->normal_balance,
            'parent_id'           => $this->parent_id,
            'path'                => (string) $this->path,
            'is_postable'         => (bool) $this->is_postable,
            'is_active'           => (bool) $this->is_active,
            'description'         => $this->description,
            'pfrs_classification' => $this->pfrs_classification,
        ];
    }
}
