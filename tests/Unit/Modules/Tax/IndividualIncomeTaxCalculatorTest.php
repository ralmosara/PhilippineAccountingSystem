<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Services\IndividualIncomeTaxCalculator;

beforeEach(function () {
    $this->calc = new IndividualIncomeTaxCalculator();
});

it('returns zero tax for taxable income at or below ₱250,000 (TRAIN 0% bracket)', function () {
    $result = $this->calc->compute('250000.00', '500000.00', false);
    expect($result['tax_due'])->toBe('0.00')
        ->and($result['method'])->toBe('graduated');
});

it('computes 15% on excess over ₱250,000', function () {
    // ₱400,000 → (400000 − 250000) × 15% = ₱22,500
    $result = $this->calc->compute('400000.00', '500000.00', false);
    expect($result['tax_due'])->toBe('22500.00');
});

it('matches the bracket boundary at ₱400,000 exactly', function () {
    // At ceiling → still in 15% bracket
    $result = $this->calc->compute('400000.00', '500000.00', false);
    expect($result['tax_due'])->toBe('22500.00');
});

it('computes ₱102,500 + 25% on excess in the ₱800k–₱2M bracket', function () {
    // ₱1,000,000 → 102500 + (1000000 − 800000) × 25% = 102500 + 50000 = ₱152,500
    $result = $this->calc->compute('1000000.00', '1500000.00', false);
    expect($result['tax_due'])->toBe('152500.00');
});

it('computes 35% in the top bracket (above ₱8M)', function () {
    // ₱10,000,000 → 2,202,500 + (10M − 8M) × 35% = 2,202,500 + 700,000 = ₱2,902,500
    $result = $this->calc->compute('10000000.00', '12000000.00', false);
    expect($result['tax_due'])->toBe('2902500.00');
});

it('applies 8% flat tax when elected and gross sales ≤ ₱3M VAT threshold', function () {
    // Self-employed with ₱2.5M gross sales electing 8% → 2,500,000 × 8% = ₱200,000
    $result = $this->calc->compute('2000000.00', '2500000.00', true);

    expect($result['method'])->toBe('flat_8pct')
        ->and($result['applied_rate_pct'])->toBe('8%')
        ->and($result['tax_due'])->toBe('200000.00');
});

it('falls back to graduated when 8% elected but gross sales exceeds ₱3M threshold', function () {
    // ₱4M gross > ₱3M VAT threshold → 8% election ignored, graduated applied
    $result = $this->calc->compute('3500000.00', '4000000.00', true);

    expect($result['method'])->toBe('graduated');
});

it('handles negative taxable income by returning zero', function () {
    $result = $this->calc->compute('-100000.00', '500000.00', false);
    expect($result['tax_due'])->toBe('0.00');
});

it('isFlatEligible returns true at and below ₱3M', function () {
    expect($this->calc->isFlatEligible('3000000.00'))->toBeTrue()
        ->and($this->calc->isFlatEligible('2999999.99'))->toBeTrue()
        ->and($this->calc->isFlatEligible('3000000.01'))->toBeFalse();
});
