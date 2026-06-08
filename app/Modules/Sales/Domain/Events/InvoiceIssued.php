<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use DateTimeImmutable;

/**
 * Emitted when a sales invoice is posted (numbered + JV posted).
 *
 * Subscribers:
 *   - Tax module's EnqueueEisSubmission listener (if BIR_EIS_ENABLED)
 *   - Inventory module (deducts stock for cash invoices with item_id)
 *   - Reporting (refresh AR aging materialized view)
 */
final readonly class InvoiceIssued
{
    public function __construct(
        public string $salesInvoiceId,
        public string $companyId,
        public string $customerId,
        public string $docNo,
        public DateTimeImmutable $invoiceDate,
        public string $journalEntryId,
        public string $total,
        public string $issuedBy,
    ) {
    }
}
