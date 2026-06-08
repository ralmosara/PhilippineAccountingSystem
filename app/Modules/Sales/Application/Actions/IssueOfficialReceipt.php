<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Actions;

use App\Modules\Accounting\Application\Actions\CreateJournalEntry;
use App\Modules\Accounting\Application\Actions\PostJournalEntry;
use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Sales\Application\Contracts\OfficialReceiptRepositoryContract;
use App\Modules\Sales\Domain\Entities\OfficialReceipt;
use App\Modules\Sales\Domain\Events\OfficialReceiptIssued;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use App\Modules\Sales\Domain\ValueObjects\OfficialReceiptId;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Issues an Official Receipt — used for:
 *   - Collecting payment on a charge sales invoice (DR Cash / CR AR)
 *   - Recording cash sales (paired with cash SI; OR alone is also acceptable
 *     for service businesses where SI isn't required)
 *
 * BIR-mandatory: sequential numbering via accounting.document_series.
 */
final readonly class IssueOfficialReceipt
{
    public function __construct(
        private OfficialReceiptRepositoryContract $receipts,
        private CreateJournalEntry $createJournal,
        private PostJournalEntry $postJournal,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(
        string $companyId,
        string $customerId,
        ?string $salesInvoiceId,
        string $documentSeriesId,
        DateTimeImmutable $receivedDate,
        string $amount,
        string $cashAccountId,
        string $arAccountId,
        string $paymentMethod,
        string $actorId,
        ?string $referenceNo = null,
        ?string $remarks = null,
    ): OfficialReceipt {
        return DB::transaction(function () use (
            $companyId, $customerId, $salesInvoiceId, $documentSeriesId,
            $receivedDate, $amount, $cashAccountId, $arAccountId,
            $paymentMethod, $actorId, $referenceNo, $remarks
        ) {
            $docNo = $this->receipts->allocateDocNo($documentSeriesId);

            $receipt = new OfficialReceipt(
                id:                new OfficialReceiptId(\Ramsey\Uuid\Uuid::uuid4()->toString()),
                companyId:         $companyId,
                customerId:        new CustomerId($customerId),
                salesInvoiceId:    $salesInvoiceId ? new SalesInvoiceId($salesInvoiceId) : null,
                documentSeriesId:  $documentSeriesId,
                docNo:             $docNo,
                receivedDate:      $receivedDate,
                amount:            Money::php($amount),
                phpAmount:         Money::php($amount),
                paymentMethod:     $paymentMethod,
                referenceNo:       $referenceNo,
                remarks:           $remarks,
                issuedBy:          $actorId,
            );

            $this->receipts->save($receipt);

            // JV: DR Cash, CR AR
            $negated = bcmul($amount, '-1', 4);
            $journalEntry = $this->createJournal->execute(
                companyId:        $companyId,
                documentSeriesId: $documentSeriesId,
                entryDate:        $receivedDate,
                lines: [
                    ['account_id' => $cashAccountId, 'debit'  => $amount,  'php_amount' => $amount],
                    ['account_id' => $arAccountId,   'credit' => $amount,  'php_amount' => $negated],
                ],
                source:        'cash_receipt',
                sourceDocId:   $receipt->id->value,
                sourceDocType: 'OfficialReceipt',
                memo:          "Official Receipt {$docNo}",
                actorId:       $actorId,
            );

            $this->postJournal->execute(
                journalEntryId: $journalEntry->id->value,
                actorId:        $actorId,
            );

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'officialreceipt.issued',
                aggregate:   'OfficialReceipt',
                aggregateId: $receipt->id->value,
                payload: [
                    'doc_no'           => $receipt->docNo,
                    'amount'           => $receipt->amount->toPhp(),
                    'payment_method'   => $paymentMethod,
                    'sales_invoice_id' => $salesInvoiceId,
                    'journal_entry_id' => $journalEntry->id->value,
                ],
            );

            $this->events->dispatch(new OfficialReceiptIssued(
                officialReceiptId: $receipt->id->value,
                companyId:         $companyId,
                customerId:        $customerId,
                salesInvoiceId:    $salesInvoiceId,
                docNo:             $receipt->docNo,
                receivedDate:      $receivedDate,
                amount:            $receipt->amount->toPhp(),
                issuedBy:          $actorId,
            ));

            return $receipt;
        });
    }
}
