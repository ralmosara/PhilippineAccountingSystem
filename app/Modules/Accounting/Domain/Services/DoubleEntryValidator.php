<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Services;

use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalException;

/**
 * Domain service — validates the double-entry rule on a JournalEntry.
 *
 * The DB-level deferred constraint is the ultimate guard, but this service
 * catches imbalance early in the Application layer with a clearer error
 * (showing the per-account breakdown) for friendlier UI messaging.
 */
final readonly class DoubleEntryValidator
{
    /**
     * @throws UnbalancedJournalException
     */
    public function validate(JournalEntry $entry): void
    {
        if ($entry->isBalanced()) {
            return;
        }

        throw new UnbalancedJournalException(
            entry: $entry,
            debitsTotal: $entry->totalDebits()->toPhp(),
            creditsTotal: $entry->totalCredits()->toPhp(),
        );
    }
}
