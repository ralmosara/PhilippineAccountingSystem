<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Domain\Entities\StockBalance;
use App\Modules\Inventory\Domain\Exceptions\NegativeStockException;
use App\Modules\Inventory\Domain\Services\MovingAverageCalculator;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;

beforeEach(function () {
    $this->calc = new MovingAverageCalculator();
    $this->at   = new DateTimeImmutable('2026-05-15');
});

it('first receipt sets the moving average to the receipt cost', function () {
    $empty = new StockBalance(itemId: 'item-1', warehouseId: 'wh-1', quantity: '0', value: Money::zero());

    $result = $this->calc->apply($empty, MovementType::Receipt, '100', Money::php('8'), $this->at);

    expect($result['new_balance']->quantity)->toBe('100.0000')
        ->and($result['new_balance']->value->toPhp())->toBe('800.00')
        ->and($result['new_balance']->averageUnitCost()->toPhp())->toBe('8.00')
        ->and($result['effective_unit_cost']->toPhp())->toBe('8.00')
        ->and($result['effective_total_cost']->toPhp())->toBe('800.00');
});

it('issue at current MA cost reduces value but leaves MA unchanged', function () {
    // 100 units @ ₱8 → issue 20 → expect 80 units, value ₱640, MA still ₱8
    $balance = new StockBalance(itemId: 'item-1', warehouseId: 'wh-1', quantity: '100', value: Money::php('800'));

    $result = $this->calc->apply($balance, MovementType::Issue, '20', Money::zero(), $this->at);

    expect($result['new_balance']->quantity)->toBe('80.0000')
        ->and($result['new_balance']->value->toPhp())->toBe('640.00')
        ->and($result['new_balance']->averageUnitCost()->toPhp())->toBe('8.00')
        ->and($result['effective_unit_cost']->toPhp())->toBe('8.00');
});

it('second receipt with different cost rolls the moving average forward (weighted)', function () {
    // 80 units @ ₱8 (value ₱640) + receipt 50 @ ₱10 → 130 units, value ₱1140, MA = 8.7692
    $balance = new StockBalance(itemId: 'item-1', warehouseId: 'wh-1', quantity: '80', value: Money::php('640'));

    $result = $this->calc->apply($balance, MovementType::Receipt, '50', Money::php('10'), $this->at);

    expect($result['new_balance']->quantity)->toBe('130.0000')
        ->and($result['new_balance']->value->toPhp())->toBe('1140.00')
        ->and($result['new_balance']->averageUnitCost()->toPhp())->toBe('8.7692');     // 1140 / 130
});

it('rejects an issue that would drive quantity below zero', function () {
    $balance = new StockBalance(itemId: 'item-1', warehouseId: 'wh-1', quantity: '10', value: Money::php('80'));

    expect(fn () => $this->calc->apply($balance, MovementType::Issue, '20', Money::zero(), $this->at))
        ->toThrow(NegativeStockException::class, 'Insufficient stock for item item-1');
});

it('rejects a zero or negative quantity input', function () {
    $balance = new StockBalance(itemId: 'item-1', warehouseId: 'wh-1', quantity: '10', value: Money::php('80'));

    expect(fn () => $this->calc->apply($balance, MovementType::Receipt, '0', Money::php('5'), $this->at))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->calc->apply($balance, MovementType::Receipt, '-5', Money::php('5'), $this->at))
        ->toThrow(InvalidArgumentException::class);
});

it('issuing the entire balance leaves quantity 0 AND forces value to 0 (no floating residual)', function () {
    $balance = new StockBalance(itemId: 'item-1', warehouseId: 'wh-1', quantity: '100', value: Money::php('800'));

    $result = $this->calc->apply($balance, MovementType::Issue, '100', Money::zero(), $this->at);

    expect($result['new_balance']->quantity)->toBe('0.0000')
        ->and($result['new_balance']->value->toPhp())->toBe('0.00');
});

it('records moved_at timestamp on the new balance', function () {
    $empty = new StockBalance(itemId: 'item-1', warehouseId: 'wh-1', quantity: '0', value: Money::zero());

    $result = $this->calc->apply($empty, MovementType::Receipt, '10', Money::php('5'), $this->at);

    expect($result['new_balance']->lastMovementAt)->toBe($this->at);
});
