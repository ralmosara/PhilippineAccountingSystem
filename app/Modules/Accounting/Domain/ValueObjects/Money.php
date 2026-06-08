<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Money — immutable value object for monetary amounts.
 *
 * Stores values as strings to preserve precision (BCMath-friendly). Never
 * use float/double for money. Comparisons and arithmetic go through this
 * class so we never lose precision in the domain layer.
 */
final readonly class Money
{
    public function __construct(
        public string $amount,                    // canonical form: "1234.5600"
        public string $currency = 'PHP',
    ) {
        if (! preg_match('/^-?\d+(\.\d{1,4})?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money amount: {$amount}");
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency: {$currency}");
        }
    }

    public static function php(string|int|float $amount): self
    {
        return new self((string) $amount, 'PHP');
    }

    public static function zero(string $currency = 'PHP'): self
    {
        return new self('0.0000', $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self(bcadd($this->amount, $other->amount, 4), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self(bcsub($this->amount, $other->amount, 4), $this->currency);
    }

    public function multiply(string $multiplier): self
    {
        return new self(bcmul($this->amount, $multiplier, 4), $this->currency);
    }

    public function negate(): self
    {
        return new self(bcmul($this->amount, '-1', 4), $this->currency);
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', 4) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', 4) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', 4) < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && bccomp($this->amount, $other->amount, 4) === 0;
    }

    public function toPhp(int $decimals = 2): string
    {
        return bcadd($this->amount, '0', $decimals);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
