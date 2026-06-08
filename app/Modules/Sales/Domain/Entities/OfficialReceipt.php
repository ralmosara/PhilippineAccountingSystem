<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use App\Modules\Sales\Domain\ValueObjects\OfficialReceiptId;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;

final readonly class OfficialReceipt
{
    public function __construct(
        public OfficialReceiptId $id,
        public string $companyId,
        public CustomerId $customerId,
        public ?SalesInvoiceId $salesInvoiceId,
        public string $documentSeriesId,
        public string $docNo,
        public DateTimeImmutable $receivedDate,
        public Money $amount,
        public Money $phpAmount,
        public string $paymentMethod,            // cash|check|bank_transfer|...
        public ?string $referenceNo = null,
        public ?string $remarks = null,
        public ?string $issuedBy = null,
    ) {
    }
}
