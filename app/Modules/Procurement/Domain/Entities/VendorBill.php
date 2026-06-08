<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Procurement\Domain\Exceptions\VendorBillAlreadyPostedException;
use App\Modules\Procurement\Domain\ValueObjects\VendorBillId;
use App\Modules\Procurement\Domain\ValueObjects\VendorId;
use DateTimeImmutable;
use DomainException;

final class VendorBill
{
    /** @var array<int, VendorBillLine> */
    public array $lines = [];

    public ?DateTimeImmutable $postedAt = null;

    public ?string $journalEntryId = null;

    public ?DateTimeImmutable $voidedAt = null;

    public string $matchStatus = 'unmatched';   // unmatched | matched | variance

    public ?DateTimeImmutable $threeWayMatchedAt = null;

    public function __construct(
        public readonly VendorBillId $id,
        public readonly string $companyId,
        public readonly VendorId $vendorId,
        public readonly ?string $purchaseOrderId,
        public readonly string $vendorInvoiceNo,
        public readonly DateTimeImmutable $vendorInvoiceDate,
        public readonly DateTimeImmutable $billDate,
        public readonly ?DateTimeImmutable $dueDate,
        public readonly string $currency = 'PHP',
        public readonly string $fxRate = '1',
        public Money $subtotal = new Money('0.0000', 'PHP'),
        public Money $vatInput = new Money('0.0000', 'PHP'),
        public Money $vatInputDeferred = new Money('0.0000', 'PHP'),
        public Money $withholdingAmount = new Money('0.0000', 'PHP'),
        public ?string $withholdingAtcCode = null,
        public ?string $withholdingRate = null,
        public Money $total = new Money('0.0000', 'PHP'),
    ) {
    }

    public function addLine(VendorBillLine $line): void
    {
        if ($this->postedAt !== null) {
            throw new DomainException('Cannot add lines to a posted vendor bill.');
        }
        $this->lines[] = $line;
    }

    public function isPosted(): bool
    {
        return $this->postedAt !== null;
    }

    public function isVoided(): bool
    {
        return $this->voidedAt !== null;
    }

    public function post(string $journalEntryId): void
    {
        if ($this->isPosted()) {
            throw new VendorBillAlreadyPostedException($this->vendorInvoiceNo);
        }
        if (count($this->lines) === 0) {
            throw new DomainException('Cannot post a vendor bill with zero lines.');
        }
        $this->postedAt = new DateTimeImmutable();
        $this->journalEntryId = $journalEntryId;
    }

    public function netPayableToVendor(): Money
    {
        // total minus withholding (we keep the WT, vendor receives net)
        return $this->total->subtract($this->withholdingAmount);
    }
}
