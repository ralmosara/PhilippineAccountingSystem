<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Payroll\Domain\Services\StatutoryDeductionCalculator;

beforeEach(function () {
    $this->calc = new StatutoryDeductionCalculator();

    // Sample SSS brackets (subset from StatutoryRatesSeeder; covers all branches)
    $this->sssBrackets = [
        ['msc_floor' =>  '5000.00', 'msc_ceiling' =>  '5249.99', 'ee_amount' =>  '225.00', 'er_amount' =>  '475.00'],
        ['msc_floor' => '20000.00', 'msc_ceiling' => '20499.99', 'ee_amount' =>  '900.00', 'er_amount' => '1900.00'],
        ['msc_floor' => '35000.00', 'msc_ceiling' => '999999.99','ee_amount' => '1575.00', 'er_amount' => '3325.00'],
    ];

    $this->phicConfig = [
        'premium_rate'   => '0.0500',
        'salary_floor'   => '10000.00',
        'salary_ceiling' => '100000.00',
    ];

    $this->hdmfConfig = [
        'ee_rate_low'   => '0.0100',
        'ee_rate_high'  => '0.0200',
        'er_rate'       => '0.0200',
        'low_threshold' => '1500.00',
        'salary_cap'    => '10000.00',
    ];
});

it('SSS: falls in first bracket for ₱5,000 monthly basic', function () {
    $result = $this->calc->computeSss(Money::php('5000'), $this->sssBrackets);
    expect($result['ee']->toPhp())->toBe('225.00')
        ->and($result['er']->toPhp())->toBe('475.00');
});

it('SSS: matches mid bracket for ₱20,000 monthly basic', function () {
    $result = $this->calc->computeSss(Money::php('20000'), $this->sssBrackets);
    expect($result['ee']->toPhp())->toBe('900.00');
});

it('SSS: caps at top bracket for salaries exceeding the ceiling', function () {
    // ₱50,000 > MSC ceiling → uses top bracket
    $result = $this->calc->computeSss(Money::php('50000'), $this->sssBrackets);
    expect($result['ee']->toPhp())->toBe('1575.00');
});

it('PhilHealth: floors salary at ₱10,000 for sub-floor earners', function () {
    // ₱5,000 → clamped to floor ₱10,000 → 5% = ₱500 total → split 50/50
    $result = $this->calc->computePhilhealth(Money::php('5000'), $this->phicConfig);
    expect($result['ee']->toPhp())->toBe('250.00')
        ->and($result['er']->toPhp())->toBe('250.00')
        ->and($result['total']->toPhp())->toBe('500.00');
});

it('PhilHealth: applies 5% rate at mid-range salaries', function () {
    // ₱50,000 × 5% = ₱2,500 → ₱1,250 each
    $result = $this->calc->computePhilhealth(Money::php('50000'), $this->phicConfig);
    expect($result['ee']->toPhp())->toBe('1250.00')
        ->and($result['total']->toPhp())->toBe('2500.00');
});

it('PhilHealth: caps salary at ₱100,000 for high earners', function () {
    // ₱200,000 → clamped to ₱100,000 → 5% = ₱5,000 → ₱2,500 each
    $result = $this->calc->computePhilhealth(Money::php('200000'), $this->phicConfig);
    expect($result['ee']->toPhp())->toBe('2500.00')
        ->and($result['total']->toPhp())->toBe('5000.00');
});

it('Pag-IBIG: applies 1% EE rate when salary ≤ ₱1,500', function () {
    // ₱1,500 × 1% = ₱15
    $result = $this->calc->computePagibig(Money::php('1500'), $this->hdmfConfig);
    expect($result['ee']->toPhp())->toBe('15.00');
});

it('Pag-IBIG: applies 2% EE rate when salary > ₱1,500', function () {
    // ₱5,000 × 2% = ₱100
    $result = $this->calc->computePagibig(Money::php('5000'), $this->hdmfConfig);
    expect($result['ee']->toPhp())->toBe('100.00')
        ->and($result['er']->toPhp())->toBe('100.00');
});

it('Pag-IBIG: caps contribution base at ₱10,000', function () {
    // ₱50,000 → capped to ₱10,000 → 2% = ₱200 EE + ₱200 ER
    $result = $this->calc->computePagibig(Money::php('50000'), $this->hdmfConfig);
    expect($result['ee']->toPhp())->toBe('200.00')
        ->and($result['er']->toPhp())->toBe('200.00');
});
