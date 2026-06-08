<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Chart-of-accounts code, e.g. "1100-01-001".
 * Format: digits and hyphens only, between 4 and 32 chars.
 */
final readonly class AccountCode
{
    public function __construct(public string $value)
    {
        $value = trim($value);

        if (! preg_match('/^[0-9][0-9\-]{3,31}$/', $value)) {
            throw new InvalidArgumentException("Invalid AccountCode: {$value}");
        }
    }

    /** Convert "1100-01-001" → ltree path "1.1100.1100_01.1100_01_001". */
    public function toLtreePath(): string
    {
        $segments = explode('-', $this->value);
        $path = [];
        $accumulator = '';

        foreach ($segments as $i => $seg) {
            $accumulator = $i === 0 ? $seg : "{$accumulator}_{$seg}";
            $path[] = $accumulator;
        }

        return implode('.', $path);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
