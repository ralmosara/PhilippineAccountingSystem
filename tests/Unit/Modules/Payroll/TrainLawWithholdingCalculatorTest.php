<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Payroll\Domain\Services\TrainLawWithholdingCalculator;

/**
 * TRAIN Law (RA 10963) Withholding Tax Calculator.
 *
 * Each test pins a specific bracket boundary against the BIR-published
 * sample computations (BIR RR 11-2018 Annex tables for semi-monthly +
 * monthly payroll frequencies).
 */
beforeEach(function () {
    $this->calc = new TrainLawWithholdingCalculator();

    // Monthly brackets per RR 11-2018 (also seeded by StatutoryRatesSeeder)
    $this->monthlyBrackets = [
        ['floor' =>        '0.00', 'ceiling' =>  '20833.00', 'base_tax' =>      '0.00', 'rate' => '0.0000'],
        ['floor' =>    '20834.00', 'ceiling' =>  '33332.00', 'base_tax' =>      '0.00', 'rate' => '0.1500'],
        ['floor' =>    '33333.00', 'ceiling' =>  '66666.00', 'base_tax' =>   '1875.00', 'rate' => '0.2000'],
        ['floor' =>    '66667.00', 'ceiling' => '166666.00', 'base_tax' =>   '8541.80', 'rate' => '0.2500'],
        ['floor' =>   '166667.00', 'ceiling' => '666666.00', 'base_tax' =>  '33541.80', 'rate' => '0.3000'],
        ['floor' =>   '666667.00', 'ceiling' => null,        'base_tax' => '183541.80', 'rate' => '0.3500'],
    ];
});

it('returns zero tax for compensation at or below ₱20,833 monthly (TRAIN 0% bracket)', function () {
    expect($this->calc->compute(Money::php('0'),         $this->monthlyBrackets)->toPhp())->toBe('0.00')
        ->and($this->calc->compute(Money::php('20833'),  $this->monthlyBrackets)->toPhp())->toBe('0.00')
        ->and($this->calc->compute(Money::php('15000'),  $this->monthlyBrackets)->toPhp())->toBe('0.00');
});

it('computes 15% on excess over ₱20,833 in the second bracket', function () {
    // BIR sample: monthly taxable comp ₱30,000 → tax = (30000 − 20833) × 15% = ₱1,375.05
    $tax = $this->calc->compute(Money::php('30000'), $this->monthlyBrackets);
    expect($tax->toPhp())->toBe('1375.05');
});

it('hits the bracket boundary at ₱33,333 with the third-bracket base tax', function () {
    // (33333 − 33333) × 20% + 1875 = ₱1,875
    expect($this->calc->compute(Money::php('33333'), $this->monthlyBrackets)->toPhp())->toBe('1875.00');
});

it('computes 20% on excess in the third bracket — Roberto Garcia example (₱46,300 taxable)', function () {
    // Demo: Roberto's gross ₱50,000 − ₱3,700 statutory_ee = ₱46,300 taxable
    // → 1875 + (46300 − 33333) × 20% = 1875 + 2593.40 = ₱4,468.40
    $tax = $this->calc->compute(Money::php('46300'), $this->monthlyBrackets);
    expect($tax->toPhp())->toBe('4468.40');
});

it('computes 25% in the fourth bracket', function () {
    // ₱100,000 → 8541.80 + (100000 − 66667) × 25% = 8541.80 + 8333.25 = ₱16,875.05
    expect($this->calc->compute(Money::php('100000'), $this->monthlyBrackets)->toPhp())->toBe('16875.05');
});

it('computes 30% in the fifth bracket', function () {
    // ₱200,000 → 33541.80 + (200000 − 166667) × 30% = 33541.80 + 9999.90 = ₱43,541.70
    expect($this->calc->compute(Money::php('200000'), $this->monthlyBrackets)->toPhp())->toBe('43541.70');
});

it('uses 35% on amounts above the top bracket', function () {
    // ₱1,000,000 → 183541.80 + (1000000 − 666667) × 35% = 183541.80 + 116666.55 = ₱300,208.35
    expect($this->calc->compute(Money::php('1000000'), $this->monthlyBrackets)->toPhp())->toBe('300208.35');
});

it('handles negative taxable income by returning zero', function () {
    expect($this->calc->compute(Money::php('-5000'), $this->monthlyBrackets)->toPhp())->toBe('0.00');
});
