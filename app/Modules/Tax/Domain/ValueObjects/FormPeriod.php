<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Period coverage for a BIR form. Different forms have different period
 * granularities — VAT (monthly + quarterly), withholding (monthly + quarterly),
 * income tax (annual). This VO normalizes the (from, to, year, month?, quarter?)
 * tuple so consumers don't have to recompute.
 */
final readonly class FormPeriod
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public int $year,
        public ?int $month = null,        // 1..12 for monthly forms
        public ?int $quarter = null,      // 1..4 for quarterly forms
    ) {
        if ($from > $to) {
            throw new InvalidArgumentException('FormPeriod: from > to');
        }
    }

    public static function month(int $year, int $month): self
    {
        $from = CarbonImmutable::create($year, $month, 1);
        $to   = $from->endOfMonth()->startOfDay();

        return new self(
            from:    new DateTimeImmutable($from->toDateString()),
            to:      new DateTimeImmutable($to->toDateString()),
            year:    $year,
            month:   $month,
            quarter: (int) ceil($month / 3),
        );
    }

    public static function quarter(int $year, int $quarter): self
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Invalid quarter: {$quarter}");
        }
        $startMonth = ($quarter - 1) * 3 + 1;
        $from = CarbonImmutable::create($year, $startMonth, 1);
        $to   = CarbonImmutable::create($year, $startMonth + 2, 1)->endOfMonth();

        return new self(
            from:    new DateTimeImmutable($from->toDateString()),
            to:      new DateTimeImmutable($to->toDateString()),
            year:    $year,
            quarter: $quarter,
        );
    }

    public static function year(int $year): self
    {
        return new self(
            from:    new DateTimeImmutable("{$year}-01-01"),
            to:      new DateTimeImmutable("{$year}-12-31"),
            year:    $year,
        );
    }

    /**
     * Cumulative period from Jan 1 through end of the given quarter — the
     * shape every quarterly ITR (1701Q / 1702Q) is computed against, because
     * BIR's quarterly returns are YTD-cumulative, not per-quarter slices.
     *
     *   Q1 → Jan 1 .. Mar 31
     *   Q2 → Jan 1 .. Jun 30
     *   Q3 → Jan 1 .. Sep 30
     *
     * The `quarter` field stays set so labels/forms still show "Q2 2026",
     * but the (from, to) range covers the whole year-to-date span.
     */
    public static function cumulativeThroughQuarter(int $year, int $quarter): self
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Invalid quarter: {$quarter}");
        }
        $endMonth = $quarter * 3;
        $from = CarbonImmutable::create($year, 1, 1);
        $to   = CarbonImmutable::create($year, $endMonth, 1)->endOfMonth();

        return new self(
            from:    new DateTimeImmutable($from->toDateString()),
            to:      new DateTimeImmutable($to->toDateString()),
            year:    $year,
            quarter: $quarter,
        );
    }

    public function label(): string
    {
        if ($this->month !== null) {
            return $this->from->format('M Y');                // 'May 2026'
        }
        if ($this->quarter !== null) {
            return "Q{$this->quarter} {$this->year}";          // 'Q2 2026'
        }
        return (string) $this->year;
    }
}
