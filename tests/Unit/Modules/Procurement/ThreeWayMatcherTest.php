<?php

declare(strict_types=1);

use App\Modules\Procurement\Domain\Services\ThreeWayMatcher;

it('flags matched when bill ≤ received quantity AND price within tolerance', function () {
    $matcher = new ThreeWayMatcher();
    $result = $matcher->evaluate([
        ['po_unit_price' => '100.00', 'po_received_qty' => '50', 'bill_unit_price' => '100.00', 'bill_qty' => '50'],
    ]);
    expect($result['status'])->toBe('matched')
        ->and($result['variances'])->toBe([]);
});

it('flags overbilled when bill quantity exceeds received quantity', function () {
    $matcher = new ThreeWayMatcher();
    $result = $matcher->evaluate([
        ['po_unit_price' => '100.00', 'po_received_qty' => '50', 'bill_unit_price' => '100.00', 'bill_qty' => '60'],
    ]);
    expect($result['status'])->toBe('variance')
        ->and($result['variances'][0]['reason'])->toBe('overbilled');
});

it('flags price variance when bill price exceeds 5% tolerance', function () {
    $matcher = new ThreeWayMatcher();  // default 5% tolerance
    // PO ₱100 vs Bill ₱110 → 10% > 5% tolerance
    $result = $matcher->evaluate([
        ['po_unit_price' => '100.00', 'po_received_qty' => '50', 'bill_unit_price' => '110.00', 'bill_qty' => '50'],
    ]);
    expect($result['status'])->toBe('variance')
        ->and($result['variances'][0]['reason'])->toBe('price_variance');
});

it('accepts price within 5% tolerance', function () {
    $matcher = new ThreeWayMatcher();
    // PO ₱100 vs Bill ₱104 → 4% < 5% tolerance
    $result = $matcher->evaluate([
        ['po_unit_price' => '100.00', 'po_received_qty' => '50', 'bill_unit_price' => '104.00', 'bill_qty' => '50'],
    ]);
    expect($result['status'])->toBe('matched');
});

it('accepts bill price below PO price (vendor discount) within tolerance', function () {
    $matcher = new ThreeWayMatcher();
    // PO ₱100 vs Bill ₱98 → -2% within ±5%
    $result = $matcher->evaluate([
        ['po_unit_price' => '100.00', 'po_received_qty' => '50', 'bill_unit_price' => '98.00', 'bill_qty' => '50'],
    ]);
    expect($result['status'])->toBe('matched');
});

it('reports per-line variance details with line number', function () {
    $matcher = new ThreeWayMatcher();
    $result = $matcher->evaluate([
        ['po_unit_price' => '100.00', 'po_received_qty' => '50', 'bill_unit_price' => '100.00', 'bill_qty' => '50'],
        ['po_unit_price' => '200.00', 'po_received_qty' => '10', 'bill_unit_price' => '250.00', 'bill_qty' => '10'],
    ]);
    expect($result['variances'])->toHaveCount(1)
        ->and($result['variances'][0]['line_no'])->toBe(2);
});

it('honors a custom price tolerance', function () {
    $strict = new ThreeWayMatcher(priceTolerancePct: '0.01');     // 1% tolerance
    // ₱100 vs ₱102 → 2% > 1% tolerance
    $result = $strict->evaluate([
        ['po_unit_price' => '100.00', 'po_received_qty' => '50', 'bill_unit_price' => '102.00', 'bill_qty' => '50'],
    ]);
    expect($result['status'])->toBe('variance');
});
