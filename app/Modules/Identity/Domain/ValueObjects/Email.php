<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObjects;

use InvalidArgumentException;

final class Email
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));

        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }

        $this->value = $value;
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
