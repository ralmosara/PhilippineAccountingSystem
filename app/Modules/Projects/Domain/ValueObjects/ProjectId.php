<?php

declare(strict_types=1);

namespace App\Modules\Projects\Domain\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class ProjectId
{
    public function __construct(public string $value)
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid ProjectId UUID: {$value}");
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
