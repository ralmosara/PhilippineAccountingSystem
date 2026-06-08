<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObjects;

use Ramsey\Uuid\Uuid;

final class UserId
{
    public readonly string $value;

    public function __construct(string $value)
    {
        if (! Uuid::isValid($value)) {
            throw new \InvalidArgumentException("Invalid UserId UUID: {$value}");
        }
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
