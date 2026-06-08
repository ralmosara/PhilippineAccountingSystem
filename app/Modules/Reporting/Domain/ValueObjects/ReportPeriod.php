<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Generic report period covering both "for the period" (P&L) and
 * "as of date" (Balance Sheet) report styles.
 *
 *   ReportPeriod::forPeriod(from, to)    — IS, Cash Flow, GJ
 *   ReportPeriod::asOf(date)             — Balance Sheet, Trial Balance (cumulative)
 */
final readonly class ReportPeriod
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public bool $isAsOf = false,
    ) {
        if ($from > $to) {
            throw new InvalidArgumentException('ReportPeriod: from > to');
        }
    }

    public static function forPeriod(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        return new self($from, $to, isAsOf: false);
    }

    public static function asOf(DateTimeImmutable $date): self
    {
        // For "as of" reports, period_from is effectively the beginning of time
        // (or fiscal-year start, depending on context). We carry the date in `to`
        // and rely on aggregator to apply the cumulative semantics.
        return new self(
            from:    new DateTimeImmutable('2000-01-01'),
            to:      $date,
            isAsOf:  true,
        );
    }

    public function label(): string
    {
        if ($this->isAsOf) {
            return 'As of '.$this->to->format('F j, Y');
        }
        return $this->from->format('F j, Y').' – '.$this->to->format('F j, Y');
    }

    public function fiscalYearStart(): DateTimeImmutable
    {
        // Default calendar year — companies with non-calendar fiscal years
        // override via the company's active fiscal_year.
        $year = (int) $this->to->format('Y');
        return new DateTimeImmutable("{$year}-01-01");
    }
}
