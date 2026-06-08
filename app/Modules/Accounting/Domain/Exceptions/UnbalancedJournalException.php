<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Modules\Accounting\Domain\Entities\JournalEntry;
use DomainException;

final class UnbalancedJournalException extends DomainException
{
    public function __construct(
        public readonly JournalEntry $entry,
        public readonly string $debitsTotal,
        public readonly string $creditsTotal,
    ) {
        parent::__construct(sprintf(
            'Journal entry %s is unbalanced: debits=%s credits=%s (diff=%s)',
            $entry->docNo,
            $debitsTotal,
            $creditsTotal,
            bcsub($debitsTotal, $creditsTotal, 2),
        ));
    }
}
