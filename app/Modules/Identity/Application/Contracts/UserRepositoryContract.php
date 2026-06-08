<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\Email;
use App\Modules\Identity\Domain\ValueObjects\UserId;

/**
 * Public surface of the Identity module's persistence layer.
 *
 * Other modules MAY consume this Contract; they may NOT touch
 * Infrastructure\Persistence directly.
 */
interface UserRepositoryContract
{
    public function findByEmail(Email $email): ?User;

    public function findById(UserId $id): ?User;

    public function recordLastLogin(UserId $id, string $ipAddress): void;
}
