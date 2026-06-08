<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\DTOs;

use DateTimeImmutable;

/**
 * AR aging snapshot for one customer as of a given date.
 *
 * Buckets follow BIR / standard accounting practice:
 *   - current     → not yet due
 *   - bucket_1_30 → 1–30 days past due
 *   - bucket_31_60
 *   - bucket_61_90
 *   - bucket_91_plus
 *
 * All amounts are decimal strings (BCMath); never floats.
 */
final readonly class CustomerArAging
{
    public function __construct(
        public string $customerId,
        public DateTimeImmutable $asOf,
        public string $current,
        public string $bucket_1_30,
        public string $bucket_31_60,
        public string $bucket_61_90,
        public string $bucket_91_plus,
        public string $total,
        /** Number of unpaid posted SIs contributing to the aging. */
        public int $unpaidInvoiceCount,
    ) {
    }
}
