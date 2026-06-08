<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Services\CorporateIncomeTaxCalculator;

beforeEach(function () {
    $this->calc = new CorporateIncomeTaxCalculator();
});

it('applies 20% MSME rate when both income ≤ ₱5M AND assets ≤ ₱100M', function () {
    // Demo: ABC Trading Inc. with ₱4.5M taxable, ₱50M assets, ₱8M gross income
    $result = $this->calc->compute(
        taxableIncome:            '4500000.00',
        grossIncome:              '8000000.00',
        totalAssetsExcludingLand: '50000000.00',
    );

    // 20% of ₱4.5M = ₱900,000; MCIT 2% of ₱8M = ₱160,000; higher = ₱900,000
    expect($result['is_msme'])->toBeTrue()
        ->and($result['applied_rate_pct'])->toBe('20.00%')
        ->and($result['regular_tax'])->toBe('900000.00')
        ->and($result['mcit_amount'])->toBe('160000.00')
        ->and($result['tax_due_higher'])->toBe('900000.00');
});

it('applies 25% regular rate when assets exceed ₱100M threshold (still under ₱5M income)', function () {
    // Income ≤ ₱5M but assets > ₱100M → NOT MSME → 25%
    $result = $this->calc->compute(
        taxableIncome:            '4500000.00',
        grossIncome:              '8000000.00',
        totalAssetsExcludingLand: '150000000.00',
    );

    expect($result['is_msme'])->toBeFalse()
        ->and($result['applied_rate_pct'])->toBe('25.00%')
        ->and($result['regular_tax'])->toBe('1125000.00');     // 4.5M × 25%
});

it('applies 25% rate when income exceeds ₱5M threshold (regardless of assets)', function () {
    $result = $this->calc->compute(
        taxableIncome:            '10000000.00',
        grossIncome:              '25000000.00',
        totalAssetsExcludingLand: '50000000.00',
    );

    expect($result['is_msme'])->toBeFalse()
        ->and($result['regular_tax'])->toBe('2500000.00');     // 10M × 25%
});

it('uses MCIT when it exceeds regular tax', function () {
    // Operating loss scenario: high gross, low/negative taxable
    // Regular tax on ₱0 taxable = ₱0; MCIT on ₱10M gross = ₱200,000
    $result = $this->calc->compute(
        taxableIncome:            '0.00',
        grossIncome:              '10000000.00',
        totalAssetsExcludingLand: '50000000.00',
    );

    expect($result['regular_tax'])->toBe('0.00')
        ->and($result['mcit_amount'])->toBe('200000.00')
        ->and($result['tax_due_higher'])->toBe('200000.00');
});

it('returns zero tax for net loss scenarios (no MCIT applied if gross is zero)', function () {
    $result = $this->calc->compute(
        taxableIncome:            '-500000.00',
        grossIncome:              '0.00',
        totalAssetsExcludingLand: '5000000.00',
    );

    expect($result['regular_tax'])->toBe('0.00')
        ->and($result['mcit_amount'])->toBe('0.00')
        ->and($result['tax_due_higher'])->toBe('0.00');
});

it('handles exactly-at-threshold values inclusively (₱5M income, ₱100M assets)', function () {
    // Both at ceiling → still MSME per CREATE Act §4 (≤, inclusive)
    $result = $this->calc->compute(
        taxableIncome:            '5000000.00',
        grossIncome:              '8000000.00',
        totalAssetsExcludingLand: '100000000.00',
    );

    expect($result['is_msme'])->toBeTrue()
        ->and($result['regular_tax'])->toBe('1000000.00');     // 5M × 20%
});
