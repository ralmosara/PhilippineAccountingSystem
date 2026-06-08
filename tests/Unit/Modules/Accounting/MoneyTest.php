<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\ValueObjects\Money;

/**
 * Money is the foundation of every monetary computation in PHA.
 * BCMath-backed; never use float/double for money in the domain layer.
 */
it('constructs from a string with up to 4 decimal places', function () {
    expect((new Money('1234.5678'))->amount)->toBe('1234.5678')
        ->and((new Money('0'))->amount)->toBe('0')
        ->and((new Money('-99.99'))->amount)->toBe('-99.99');
});

it('rejects malformed amounts and invalid currency codes', function () {
    expect(fn () => new Money('not-a-number'))
        ->toThrow(InvalidArgumentException::class, 'Invalid money amount');

    expect(fn () => new Money('1.234567'))     // 5+ dp
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new Money('100', 'usd'))   // lowercase ISO
        ->toThrow(InvalidArgumentException::class, 'Invalid currency');
});

it('adds and subtracts within the same currency', function () {
    $a = Money::php('100.50');
    $b = Money::php('25.75');

    expect($a->add($b)->amount)->toBe('126.2500')
        ->and($a->subtract($b)->amount)->toBe('74.7500');
});

it('rejects arithmetic across mismatched currencies', function () {
    expect(fn () => Money::php('100')->add(new Money('50', 'USD')))
        ->toThrow(InvalidArgumentException::class, 'Currency mismatch');
});

it('multiplies, negates, and detects zero/positive/negative', function () {
    $m = Money::php('100');

    expect($m->multiply('1.5')->amount)->toBe('150.0000')
        ->and($m->negate()->amount)->toBe('-100.0000')
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and($m->isPositive())->toBeTrue()
        ->and($m->negate()->isNegative())->toBeTrue();
});

it('compares for equality with BCMath precision', function () {
    expect(Money::php('100')->equals(Money::php('100.00')))->toBeTrue()
        ->and(Money::php('100')->equals(Money::php('100.0001')))->toBeFalse()
        ->and(Money::php('100')->equals(new Money('100', 'USD')))->toBeFalse();
});

it('toPhp truncates to specified decimals (default 2)', function () {
    expect(Money::php('123.4567')->toPhp())->toBe('123.45')        // BCMath truncates, not rounds
        ->and(Money::php('123.4567')->toPhp(4))->toBe('123.4567');
});
