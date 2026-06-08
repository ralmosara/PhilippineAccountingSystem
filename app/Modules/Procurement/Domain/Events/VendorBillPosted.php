<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Events;

use DateTimeImmutable;

final readonly class VendorBillPosted
{
    public function __construct(
        public string $vendorBillId,
        public string $companyId,
        public string $vendorId,
        public string $vendorInvoiceNo,
        public DateTimeImmutable $billDate,
        public string $journalEntryId,
        public string $total,
        public string $withholdingAmount,
        public ?string $withholdingAtcCode,
        public string $postedBy,
    ) {
    }

    public function hasWithholding(): bool
    {
        return $this->withholdingAtcCode !== null
            && bccomp($this->withholdingAmount, '0', 2) > 0;
    }
}
