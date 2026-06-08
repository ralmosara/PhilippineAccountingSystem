<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Domain\Events;

/**
 * Fired after a monthly depreciation batch is posted for a company.
 */
final readonly class DepreciationPosted
{
    public function __construct(
        public string $companyId,
        public int    $year,
        public int    $month,
        public int    $assetsProcessed,
        public string $totalDepreciation,   // numeric string, sum of all charges
        public string $postedBy,
        public \DateTimeImmutable $postedAt,
    ) {
    }
}
