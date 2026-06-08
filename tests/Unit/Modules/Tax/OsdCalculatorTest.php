<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Services\OsdCalculator;

/**
 * Optional Standard Deduction (OSD) — NIRC §34(L), RR 2-2010.
 *
 * 40% rate is FIXED by statute (RA 9504); these tests pin the math so that
 * if anyone "tweaks" the rate constant the whole test sheet fires red.
 *
 * Individual basis: gross sales (1701)
 * Corporate basis:  gross income = gross sales − returns − cost of sales (1702-RT)
 */
beforeEach(function () {
    $this->osd = new OsdCalculator();
});

it('exposes the statutory 40% rate as a constant (no magic numbers in callers)', function () {
    expect(OsdCalculator::RATE)->toBe('0.40');
});

it('individual OSD = 40% × gross sales (NOT gross income)', function () {
    // ₱1,000,000 gross sales → ₱400,000 OSD, regardless of cost structure.
    expect($this->osd->forIndividual('1000000.00'))->toBe('400000.00');
});

it('individual OSD handles odd decimals with BCMath precision (no float drift)', function () {
    // ₱123,456.78 × 0.40 = ₱49,382.71  (truncate-to-2dp, not round)
    expect($this->osd->forIndividual('123456.78'))->toBe('49382.71');
});

it('individual OSD on zero sales is zero (no negative, no error)', function () {
    expect($this->osd->forIndividual('0.00'))->toBe('0.00');
});

it('individual OSD rejects negative gross sales', function () {
    expect(fn () => $this->osd->forIndividual('-1000.00'))
        ->toThrow(InvalidArgumentException::class, 'cannot be negative');
});

it('corporate OSD = 40% × (gross sales − returns − cost of sales)', function () {
    // Gross 5,000,000 − returns 200,000 − COGS 3,000,000 = gross income 1,800,000
    // OSD = 1,800,000 × 40% = 720,000
    expect($this->osd->forCorporate(
        grossSales:   '5000000.00',
        salesReturns: '200000.00',
        costOfSales:  '3000000.00',
    ))->toBe('720000.00');
});

it('corporate OSD on a loss-making cost structure yields 0 (never negative)', function () {
    // COGS > net sales → gross income negative → OSD clamped to 0
    expect($this->osd->forCorporate(
        grossSales:   '100000.00',
        salesReturns: '0.00',
        costOfSales:  '150000.00',
    ))->toBe('0.00');
});

it('corporate OSD when gross income is exactly zero yields 0', function () {
    expect($this->osd->forCorporate(
        grossSales:   '500000.00',
        salesReturns: '0.00',
        costOfSales:  '500000.00',
    ))->toBe('0.00');
});

it('corporate OSD rejects any negative input', function () {
    expect(fn () => $this->osd->forCorporate('-1.00', '0.00', '0.00'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->osd->forCorporate('100.00', '-1.00', '0.00'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->osd->forCorporate('100.00', '0.00', '-1.00'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to elect OSD together with the 8% flat regime (RR 8-2018 § 3)', function () {
    expect(fn () => $this->osd->assertNotCombinedWithFlat8Percent(useOsd: true, electFlat8Percent: true))
        ->toThrow(InvalidArgumentException::class, 'Cannot elect both OSD');
});

it('allows OSD alone (no 8% flat)', function () {
    expect(fn () => $this->osd->assertNotCombinedWithFlat8Percent(useOsd: true, electFlat8Percent: false))
        ->not->toThrow(Exception::class);
});

it('allows 8% flat alone (no OSD)', function () {
    expect(fn () => $this->osd->assertNotCombinedWithFlat8Percent(useOsd: false, electFlat8Percent: true))
        ->not->toThrow(Exception::class);
});

it('allows neither (graduated + itemized — the default regime)', function () {
    expect(fn () => $this->osd->assertNotCombinedWithFlat8Percent(useOsd: false, electFlat8Percent: false))
        ->not->toThrow(Exception::class);
});

/* ── Worked CPA-board-style examples (golden values) ───────────────────── */

it('CPA exam scenario: freelance professional, ₱2.5M gross — OSD = ₱1.0M deduction', function () {
    // Common board-exam scenario:
    //   Maria, self-employed CPA, gross professional fees ₱2,500,000
    //   Itemized: hard to track receipts → elects OSD
    //   OSD = 2,500,000 × 40% = 1,000,000 → taxable = ₱1.5M (before tax brackets)
    expect($this->osd->forIndividual('2500000.00'))->toBe('1000000.00');
});

it('CPA exam scenario: trading corp, gross ₱20M / returns ₱500k / COGS ₱14M → OSD ₱2.2M', function () {
    // Net sales = 19,500,000
    // Gross income = 19,500,000 − 14,000,000 = 5,500,000
    // OSD = 5,500,000 × 40% = 2,200,000
    expect($this->osd->forCorporate(
        grossSales:   '20000000.00',
        salesReturns: '500000.00',
        costOfSales:  '14000000.00',
    ))->toBe('2200000.00');
});
