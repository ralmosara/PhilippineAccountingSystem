<?php

declare(strict_types=1);

use App\Modules\Sales\Domain\Entities\Customer;
use App\Modules\Sales\Domain\Services\VatCalculator;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;

/**
 * VAT computation for Philippine sales invoices.
 *
 * Covers all four Philippine VAT scenarios:
 *   - Standard 12% VAT (NIRC Sec. 106-108)
 *   - Senior Citizen (RA 9994) / PWD (RA 10754) — 20% discount + VAT exempt
 *   - Government customers — 5% final VAT withheld (NIRC Sec. 114(C))
 *   - Zero-rated / exempt mix
 */
beforeEach(function () {
    $this->calc = new VatCalculator();

    $this->regular = new Customer(
        id:               new CustomerId('018f0000-0000-7000-8000-000000000010'),
        companyId:        '018f0000-0000-7000-8000-000000000001',
        customerNo:       'CUST-000001',
        registeredName:   'XYZ Manufacturing Corp.',
        tin:              '012-345-678-000',
        isVatRegistered:  true,
        isGovernment:     false,
        isSeniorCitizen:  false,
        isPwd:            false,
    );
});

it('computes standard 12% VAT on a single vatable line', function () {
    // ₱10,000 vatable → ₱1,200 VAT → total ₱11,200
    $totals = $this->calc->compute(
        [['subtotal' => '10000', 'kind' => 'vat_output']],
        $this->regular,
    );

    expect($totals['vatable_sales']->toPhp())->toBe('10000.00')
        ->and($totals['vat_amount']->toPhp())->toBe('1200.00')
        ->and($totals['total']->toPhp())->toBe('11200.00')
        ->and($totals['withheld_vat']->toPhp())->toBe('0.00');
});

it('keeps zero-rated and exempt lines outside the VAT base', function () {
    // ₱10,000 vatable + ₱5,000 zero-rated + ₱2,000 exempt
    // VAT = 10000 × 12% = ₱1,200; Total = 10000 + 5000 + 2000 + 1200 = ₱18,200
    $totals = $this->calc->compute([
        ['subtotal' => '10000', 'kind' => 'vat_output'],
        ['subtotal' =>  '5000', 'kind' => 'vat_zero'],
        ['subtotal' =>  '2000', 'kind' => 'vat_exempt'],
    ], $this->regular);

    expect($totals['vatable_sales']->toPhp())->toBe('10000.00')
        ->and($totals['vat_zero_rated_sales']->toPhp())->toBe('5000.00')
        ->and($totals['vat_exempt_sales']->toPhp())->toBe('2000.00')
        ->and($totals['vat_amount']->toPhp())->toBe('1200.00')
        ->and($totals['total']->toPhp())->toBe('18200.00');
});

it('senior citizen: collapses lines to exempt + applies 20% discount + zero VAT', function () {
    $senior = new Customer(
        id:               new CustomerId('018f0000-0000-7000-8000-000000000011'),
        companyId:        '018f0000-0000-7000-8000-000000000001',
        customerNo:       'CUST-000002',
        registeredName:   'Maria Santos',
        tin:              null,
        isVatRegistered:  false,
        isGovernment:     false,
        isSeniorCitizen:  true,
        isPwd:            false,
    );

    // ₱10,000 gross → 20% disc = ₱2,000 → net ₱8,000, VAT 0
    $totals = $this->calc->compute(
        [['subtotal' => '10000', 'kind' => 'vat_output']],
        $senior,
    );

    expect($totals['vat_exempt_sales']->toPhp())->toBe('8000.00')
        ->and($totals['vatable_sales']->toPhp())->toBe('0.00')
        ->and($totals['vat_amount']->toPhp())->toBe('0.00')
        ->and($totals['senior_pwd_discount']->toPhp())->toBe('2000.00')
        ->and($totals['total']->toPhp())->toBe('8000.00');
});

it('PWD: receives the same treatment as senior citizen', function () {
    $pwd = new Customer(
        id:               new CustomerId('018f0000-0000-7000-8000-000000000012'),
        companyId:        '018f0000-0000-7000-8000-000000000001',
        customerNo:       'CUST-000003',
        registeredName:   'Juan Dela Cruz (PWD)',
        tin:              null,
        isVatRegistered:  false,
        isGovernment:     false,
        isSeniorCitizen:  false,
        isPwd:            true,
    );

    $totals = $this->calc->compute(
        [['subtotal' => '5000', 'kind' => 'vat_output']],
        $pwd,
    );

    expect($totals['senior_pwd_discount']->toPhp())->toBe('1000.00')
        ->and($totals['total']->toPhp())->toBe('4000.00')
        ->and($totals['vat_amount']->toPhp())->toBe('0.00');
});

it('government customer: applies 5% VAT withholding on top of 12% VAT', function () {
    $gov = new Customer(
        id:               new CustomerId('018f0000-0000-7000-8000-000000000013'),
        companyId:        '018f0000-0000-7000-8000-000000000001',
        customerNo:       'CUST-000004',
        registeredName:   'Department of Public Works and Highways',
        tin:              '000-000-000-005',
        isVatRegistered:  true,
        isGovernment:     true,
        isSeniorCitizen:  false,
        isPwd:            false,
    );

    // ₱10,000 vatable → ₱1,200 VAT → ₱500 withheld → net to receive ₱10,700
    $totals = $this->calc->compute(
        [['subtotal' => '10000', 'kind' => 'vat_output']],
        $gov,
    );

    expect($totals['vatable_sales']->toPhp())->toBe('10000.00')
        ->and($totals['vat_amount']->toPhp())->toBe('1200.00')
        ->and($totals['withheld_vat']->toPhp())->toBe('500.00')
        ->and($totals['total']->toPhp())->toBe('10700.00');
});

it('multiline mixed case adds correctly across kinds', function () {
    // Two vatable lines + one exempt
    $totals = $this->calc->compute([
        ['subtotal' => '1000', 'kind' => 'vat_output'],
        ['subtotal' => '2000', 'kind' => 'vat_output'],
        ['subtotal' =>  '500', 'kind' => 'vat_exempt'],
    ], $this->regular);

    expect($totals['vatable_sales']->toPhp())->toBe('3000.00')
        ->and($totals['vat_amount']->toPhp())->toBe('360.00')
        ->and($totals['vat_exempt_sales']->toPhp())->toBe('500.00')
        ->and($totals['total']->toPhp())->toBe('3860.00');
});
