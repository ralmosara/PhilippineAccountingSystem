<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel
 */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'email'        => $this->email,
            'full_name'    => $this->full_name,
            'mfa_enabled'  => (bool) $this->mfa_enabled,
            'is_active'    => (bool) $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles'        => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions'  => $this->whenLoaded('permissions', fn () => $this->getAllPermissions()->pluck('name')),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
