<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Events;

use DateTimeImmutable;

/**
 * Domain event emitted when a journal entry is posted.
 *
 * Other modules (Reporting, Tax) may subscribe to react: refresh trial
 * balance materialized view, accumulate VAT for the period, etc.
 *
 * Phase 1: Laravel events (in-process). Phase 2 microservices: same
 * payload published to Redis Streams / RabbitMQ.
 */
final readonly class JournalPosted
{
    public function __construct(
        public string $journalEntryId,
        public string $companyId,
        public string $docNo,
        public string $fiscalPeriodId,
        public DateTimeImmutable $postedAt,
        public string $postedBy,
        public string $source,
    ) {
    }
}
