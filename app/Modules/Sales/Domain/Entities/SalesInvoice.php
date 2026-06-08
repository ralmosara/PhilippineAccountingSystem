<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Sales\Domain\Exceptions\InvoiceAlreadyVoidedException;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;
use DomainException;

final class SalesInvoice
{
    /** @var array<int, SalesInvoiceLine> */
    public array $lines = [];

    public ?DateTimeImmutable $postedAt = null;

    public ?string $postedBy = null;

    public ?string $journalEntryId = null;

    public ?DateTimeImmutable $voidedAt = null;

    public ?string $voidReason = null;

    public ?string $voidedBy = null;

    public function __construct(
        public readonly SalesInvoiceId $id,
        public readonly string $companyId,
        public readonly CustomerId $customerId,
        public readonly string $documentSeriesId,
        public readonly string $docNo,
        public readonly string $docKind,            // 'cash' | 'charge'
        public readonly DateTimeImmutable $invoiceDate,
        public readonly ?DateTimeImmutable $dueDate = null,
        public readonly string $currency = 'PHP',
        public readonly string $fxRate = '1',
        // Pre-computed totals (set by IssueSalesInvoice action via VatCalculator)
        public Money $subtotal = new Money('0.0000', 'PHP'),
        public Money $vatExemptSales = new Money('0.0000', 'PHP'),
        public Money $vatZeroRatedSales = new Money('0.0000', 'PHP'),
        public Money $vatableSales = new Money('0.0000', 'PHP'),
        public Money $vatAmount = new Money('0.0000', 'PHP'),
        public Money $discountAmount = new Money('0.0000', 'PHP'),
        public Money $seniorPwdDiscount = new Money('0.0000', 'PHP'),
        public Money $withheldVat = new Money('0.0000', 'PHP'),
        public Money $total = new Money('0.0000', 'PHP'),
    ) {
    }

    public function addLine(SalesInvoiceLine $line): void
    {
        if ($this->postedAt !== null) {
            throw new DomainException('Cannot add lines to a posted invoice.');
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

    public function post(string $postedBy, string $journalEntryId): void
    {
        if ($this->isPosted()) {
            throw new DomainException("Invoice {$this->docNo} is already posted.");
        }
        if (count($this->lines) === 0) {
            throw new DomainException('Cannot post an invoice with zero lines.');
        }
        $this->postedAt = new DateTimeImmutable();
        $this->postedBy = $postedBy;
        $this->journalEntryId = $journalEntryId;
    }

    public function void(string $reason, string $voidedBy): void
    {
        if (! $this->isPosted()) {
            throw new DomainException('Cannot void an unposted invoice; delete the draft instead.');
        }
        if ($this->isVoided()) {
            throw new InvoiceAlreadyVoidedException($this->docNo);
        }
        if (trim($reason) === '') {
            throw new DomainException('Void reason is required.');
        }

        $this->voidedAt = new DateTimeImmutable();
        $this->voidReason = $reason;
        $this->voidedBy = $voidedBy;
    }
}
