<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\ValueObjects\FormPeriod;

/**
 * `cumulativeThroughQuarter()` produces the (Jan 1 → end-of-Q) span every
 * quarterly ITR generator queries against. The from-date must always be
 * Jan 1; the to-date must hit the month-end day exactly (BIR filters are
 * date-inclusive); the `quarter` label must survive so PDFs/labels still
 * say "Q2 2026" rather than "as of Jun 30, 2026".
 */

it('Q1 → Jan 1 .. Mar 31', function () {
    $p = FormPeriod::cumulativeThroughQuarter(2026, 1);

    expect($p->from->format('Y-m-d'))->toBe('2026-01-01')
        ->and($p->to->format('Y-m-d'))->toBe('2026-03-31')
        ->and($p->year)->toBe(2026)
        ->and($p->quarter)->toBe(1)
        ->and($p->month)->toBeNull();
});

it('Q2 → Jan 1 .. Jun 30 (cumulative, not just Apr–Jun)', function () {
    $p = FormPeriod::cumulativeThroughQuarter(2026, 2);

    expect($p->from->format('Y-m-d'))->toBe('2026-01-01')
        ->and($p->to->format('Y-m-d'))->toBe('2026-06-30')
        ->and($p->quarter)->toBe(2);
});

it('Q3 → Jan 1 .. Sep 30', function () {
    $p = FormPeriod::cumulativeThroughQuarter(2026, 3);

    expect($p->from->format('Y-m-d'))->toBe('2026-01-01')
        ->and($p->to->format('Y-m-d'))->toBe('2026-09-30');
});

it('Q4 → Jan 1 .. Dec 31 (matches FormPeriod::year)', function () {
    // Q4 is structurally the same as the full year. The cumulativeThroughQuarter
    // factory accepts it for symmetry; the quarterly Action layer rejects Q4
    // separately because Q4 is filed via the annual return.
    $p = FormPeriod::cumulativeThroughQuarter(2026, 4);

    expect($p->from->format('Y-m-d'))->toBe('2026-01-01')
        ->and($p->to->format('Y-m-d'))->toBe('2026-12-31')
        ->and($p->quarter)->toBe(4);
});

it('rejects invalid quarter numbers', function () {
    expect(fn () => FormPeriod::cumulativeThroughQuarter(2026, 0))
        ->toThrow(InvalidArgumentException::class, 'Invalid quarter')
        ->and(fn () => FormPeriod::cumulativeThroughQuarter(2026, 5))
        ->toThrow(InvalidArgumentException::class, 'Invalid quarter')
        ->and(fn () => FormPeriod::cumulativeThroughQuarter(2026, -1))
        ->toThrow(InvalidArgumentException::class);
});

it('label preserves the quarter tag (not date range) for display purposes', function () {
    expect(FormPeriod::cumulativeThroughQuarter(2026, 2)->label())->toBe('Q2 2026');
});

it('non-Gregorian fiscal years still anchor on Jan 1 (calendar default)', function () {
    // The system uses calendar fiscal years as default; cumulativeThroughQuarter
    // does NOT know about non-calendar fiscal years. Companies with off-calendar
    // FYs override at the company-fiscal_year level, not here.
    $p = FormPeriod::cumulativeThroughQuarter(2027, 1);
    expect($p->from->format('Y-m-d'))->toBe('2027-01-01');
});

it('handles leap-year February (Q1 of a leap year still ends Mar 31, not Feb 29)', function () {
    $p = FormPeriod::cumulativeThroughQuarter(2028, 1);             // 2028 is a leap year
    expect($p->to->format('Y-m-d'))->toBe('2028-03-31');
});
