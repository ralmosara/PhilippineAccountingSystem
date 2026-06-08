<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuthenticatedUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{user: \App\Modules\Identity\Domain\Entities\User, token: string, expires_at: \DateTimeImmutable, requires_mfa: bool} $data */
        $data = $this->resource;

        return [
            'token'        => $data['token'],
            'expires_at'   => $data['expires_at']->format(\DateTimeInterface::ATOM),
            'requires_mfa' => $data['requires_mfa'],
            'user' => [
                'id'        => $data['user']->id->value,
                'email'     => $data['user']->email->value,
                'full_name' => $data['user']->fullName,
            ],
        ];
    }
}
