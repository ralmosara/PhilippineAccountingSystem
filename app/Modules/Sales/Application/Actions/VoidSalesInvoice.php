<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Actions;

use App\Modules\Accounting\Application\Actions\ReverseJournalEntry;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Sales\Application\Contracts\SalesInvoiceRepositoryContract;
use App\Modules\Sales\Application\Exceptions\InvoiceNotFoundException;
use App\Modules\Sales\Domain\Events\InvoiceVoided;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Voiding a posted sales invoice:
 *   1. Reverse the original journal entry (creates a JV with flipped d/c)
 *   2. Mark the invoice as voided (soft-void; sequence_no is preserved)
 *   3. Emit InvoiceVoided so EIS / Inventory subscribers can react
 *   4. Audit
 *
 * The original invoice row remains in place — BIR auditors can see the
 * voided number and the void reason. The reversal JV provides the
 * accounting offset.
 */
final readonly class VoidSalesInvoice
{
    public function __construct(
        private SalesInvoiceRepositoryContract $invoices,
        private ReverseJournalEntry $reverseJournal,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(
        string $salesInvoiceId,
        string $reason,
        string $actorId,
        ?DateTimeImmutable $voidDate = null,
    ): void {
        $invoice = $this->invoices->findById(new SalesInvoiceId($salesInvoiceId))
            ?? throw new InvoiceNotFoundException($salesInvoiceId);

        $voidDate ??= new DateTimeImmutable();

        DB::transaction(function () use ($invoice, $reason, $actorId, $voidDate) {
            // 1. Reverse the JV
            $reversal = $this->reverseJournal->execute(
                originalJournalEntryId: $invoice->journalEntryId,
                reversalDate:           $voidDate,
                reason:                 "Void of {$invoice->docNo}: {$reason}",
                actorId:                $actorId,
            );

            // 2. Mark the invoice voided
            $invoice->void(reason: $reason, voidedBy: $actorId);
            $this->invoices->save($invoice);

            // 3. Audit + event
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $invoice->companyId,
                eventType:   'salesinvoice.voided',
                aggregate:   'SalesInvoice',
                aggregateId: $invoice->id->value,
                payload: [
                    'doc_no'                   => $invoice->docNo,
                    'reason'                   => $reason,
                    'reversal_journal_entry_id' => $reversal->id->value,
                ],
            );

            $this->events->dispatch(new InvoiceVoided(
                salesInvoiceId:        $invoice->id->value,
                companyId:             $invoice->companyId,
                docNo:                 $invoice->docNo,
                reversalJournalEntryId: $reversal->id->value,
                voidedAt:              $invoice->voidedAt,
                voidedBy:              $actorId,
                reason:                $reason,
            ));
        });
    }
}
