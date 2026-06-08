<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\Email;

interface AuthenticatorContract
{
    /**
     * Verify credentials and return the User entity, or null on failure.
     */
    public function attempt(Email $email, string $password): ?User;

    /**
     * Issue a Sanctum token (or equivalent) for a User.
     *
     * @return array{token: string, expires_at: \DateTimeImmutable}
     */
    public function issueToken(User $user, string $deviceName): array;

    public function revokeToken(string $tokenId): void;
}
