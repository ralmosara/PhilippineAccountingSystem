<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use DateTimeImmutable;

final readonly class OfficialReceiptIssued
{
    public function __construct(
        public string $officialReceiptId,
        public string $companyId,
        public string $customerId,
        public ?string $salesInvoiceId,
        public string $docNo,
        public DateTimeImmutable $receivedDate,
        public string $amount,
        public string $issuedBy,
    ) {
    }
}
