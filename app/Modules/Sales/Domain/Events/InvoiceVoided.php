<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use DateTimeImmutable;

final readonly class InvoiceVoided
{
    public function __construct(
        public string $salesInvoiceId,
        public string $companyId,
        public string $docNo,
        public string $reversalJournalEntryId,
        public DateTimeImmutable $voidedAt,
        public string $voidedBy,
        public string $reason,
    ) {
    }
}
