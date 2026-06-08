<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Domain\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * AssetId — typed UUID wrapper for fixed asset identifiers.
 */
final readonly class AssetId
{
    public function __construct(public string $value)
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid AssetId UUID: {$value}");
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
