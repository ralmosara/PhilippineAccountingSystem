<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entities;

use App\Modules\Identity\Domain\ValueObjects\Email;
use App\Modules\Identity\Domain\ValueObjects\UserId;

/**
 * Pure domain entity — no framework, no persistence.
 *
 * Backed by an Eloquent model in Infrastructure for storage; this entity
 * carries the business invariants and is what Application/Actions operate on.
 */
final class User
{
    public function __construct(
        public readonly UserId $id,
        public readonly string $companyId,
        public readonly Email $email,
        public readonly string $fullName,
        public readonly bool $mfaEnabled,
        public readonly bool $isActive,
    ) {
    }

    public function requiresMfa(): bool
    {
        // Sensitive roles must use MFA; resolved at higher layer via policy
        return $this->mfaEnabled;
    }
}
