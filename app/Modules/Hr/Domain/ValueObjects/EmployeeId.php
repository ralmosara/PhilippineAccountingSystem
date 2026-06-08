<?php

declare(strict_types=1);

namespace App\Modules\Hr\Domain\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class EmployeeId
{
    public function __construct(public string $value)
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid EmployeeId UUID: {$value}");
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
