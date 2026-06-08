<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Procurement\Domain\Services\WithholdingTaxCalculator;

beforeEach(function () {
    $this->calc = new WithholdingTaxCalculator();
});

it('computes withholding tax as rate × base for a professional fee (WI010 5%)', function () {
    // Atty. Reyes Law Office: ₱50,000 fee × 5% = ₱2,500
    $result = $this->calc->compute(Money::php('50000'), 'WI010', '0.0500');

    expect($result['tax_withheld']->toPhp())->toBe('2500.00')
        ->and($result['rate'])->toBe('0.0500')
        ->and($result['base']->toPhp())->toBe('50000.00');
});

it('computes 1% WT for top-withholding-agent goods purchases (WC010)', function () {
    // Acme Office Supplies: ₱10,000 × 1% = ₱100
    $result = $this->calc->compute(Money::php('10000'), 'WC010', '0.0100');
    expect($result['tax_withheld']->toPhp())->toBe('100.00');
});

it('computes 2% WT for contractors (WC156)', function () {
    // PrintWorks Contractor: ₱100,000 × 2% = ₱2,000
    $result = $this->calc->compute(Money::php('100000'), 'WC156', '0.0200');
    expect($result['tax_withheld']->toPhp())->toBe('2000.00');
});

it('returns zero base + zero withheld for a zero income payment', function () {
    $result = $this->calc->compute(Money::php('0'), 'WI010', '0.0500');
    expect($result['tax_withheld']->toPhp())->toBe('0.00');
});

it('baseFor returns subtotal by default (net-of-VAT per RR 11-2018)', function () {
    $base = $this->calc->baseFor(Money::php('50000'), Money::php('6000'), includeVat: false);
    expect($base->toPhp())->toBe('50000.00');
});

it('baseFor returns gross-of-VAT when includeVat is true (e.g. rentals)', function () {
    $base = $this->calc->baseFor(Money::php('50000'), Money::php('6000'), includeVat: true);
    expect($base->toPhp())->toBe('56000.00');
});
