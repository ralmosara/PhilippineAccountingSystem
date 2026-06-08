<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Accounting\Application\Actions\CreateJournalEntry;
use App\Modules\Accounting\Application\Actions\PostJournalEntry;
use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Procurement\Application\Contracts\AtcCodeProviderContract;
use App\Modules\Procurement\Application\Contracts\VendorBillRepositoryContract;
use App\Modules\Procurement\Application\Contracts\VendorRepositoryContract;
use App\Modules\Procurement\Application\Exceptions\VendorNotFoundException;
use App\Modules\Procurement\Domain\Entities\VendorBill;
use App\Modules\Procurement\Domain\Entities\VendorBillLine;
use App\Modules\Procurement\Domain\Events\VendorBillPosted;
use App\Modules\Procurement\Domain\Services\WithholdingTaxCalculator;
use App\Modules\Procurement\Domain\ValueObjects\VendorBillId;
use App\Modules\Procurement\Domain\ValueObjects\VendorId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Posts a vendor bill end-to-end:
 *   1. Validate vendor exists; resolve ATC code + rate (from vendor default
 *      or override at line/header level)
 *   2. Compute withholding tax (WithholdingTaxCalculator)
 *   3. Persist procurement.vendor_bills + lines
 *   4. Create draft JV:
 *        DR  Expense Account(s)              (subtotal)
 *        DR  Input VAT                       (vat_input)
 *        CR  AP — Vendor                     (total)
 *        CR  Withholding Tax Payable         (withholding_amount)
 *   5. Post JV (deferred balance constraint asserts at COMMIT)
 *   6. Mark bill posted; link journal_entry_id
 *   7. Emit VendorBillPosted (a Form2307 listener may auto-issue the cert)
 *   8. Hash-chained audit event
 */
final readonly class PostVendorBill
{
    public function __construct(
        private VendorBillRepositoryContract $bills,
        private VendorRepositoryContract $vendors,
        private AtcCodeProviderContract $atcCodes,
        private WithholdingTaxCalculator $withholdingCalc,
        private CreateJournalEntry $createJournal,
        private PostJournalEntry $postJournal,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    /**
     * @param  array<int, array{
     *     description: string,
     *     quantity: string|int|float,
     *     unit_price: string|int|float,
     *     vat_amount?: string|int|float,
     *     expense_account_id: string,
     *     tax_code_id?: string|null,
     *     item_id?: string|null,
     *     project_id?: string|null,
     *     purchase_order_line_id?: string|null,
     * }>  $lines
     */
    public function execute(
        string $companyId,
        string $vendorId,
        ?string $purchaseOrderId,
        string $vendorInvoiceNo,
        DateTimeImmutable $vendorInvoiceDate,
        DateTimeImmutable $billDate,
        array $lines,
        string $apAccountId,
        string $vatInputAccountId,
        string $withholdingPayableAccountId,
        string $jvDocumentSeriesId,
        string $actorId,
        ?string $atcCodeOverride = null,
        ?DateTimeImmutable $dueDate = null,
        string $currency = 'PHP',
    ): VendorBill {
        $vendor = $this->vendors->findById(new VendorId($vendorId))
            ?? throw new VendorNotFoundException($vendorId);

        return DB::transaction(function () use (
            $companyId, $vendor, $purchaseOrderId, $vendorInvoiceNo,
            $vendorInvoiceDate, $billDate, $dueDate, $lines,
            $apAccountId, $vatInputAccountId, $withholdingPayableAccountId,
            $jvDocumentSeriesId, $actorId, $atcCodeOverride, $currency
        ) {
            // 1. Compute totals from lines
            $subtotal = '0';
            $vatTotal = '0';
            foreach ($lines as $row) {
                $lineSubtotal = bcmul((string) $row['quantity'], (string) $row['unit_price'], 4);
                $subtotal = bcadd($subtotal, $lineSubtotal, 4);
                if (isset($row['vat_amount'])) {
                    $vatTotal = bcadd($vatTotal, (string) $row['vat_amount'], 4);
                }
            }
            $grandTotal = bcadd($subtotal, $vatTotal, 4);

            // 2. Resolve ATC code & withholding
            $atcCode = $atcCodeOverride ?? $vendor->defaultAtcCode;
            $withholding = ['tax_withheld' => Money::zero(), 'rate' => '0', 'base' => Money::zero()];

            if ($atcCode !== null) {
                $atc = $this->atcCodes->find($atcCode);
                if ($atc === null) {
                    throw new \RuntimeException("Unknown ATC code: {$atcCode}");
                }
                $withholding = $this->withholdingCalc->compute(
                    base:     Money::php($subtotal),    // net of VAT (default rule)
                    atcCode:  $atcCode,
                    rate:     $atc['rate'],
                );
            }

            // 3. Build the VendorBill entity
            $bill = new VendorBill(
                id:                  VendorBillId::generate(),
                companyId:           $companyId,
                vendorId:            $vendor->id,
                purchaseOrderId:     $purchaseOrderId,
                vendorInvoiceNo:     $vendorInvoiceNo,
                vendorInvoiceDate:   $vendorInvoiceDate,
                billDate:            $billDate,
                dueDate:             $dueDate,
                currency:            $currency,
                subtotal:            Money::php($subtotal),
                vatInput:            Money::php($vatTotal),
                withholdingAmount:   $withholding['tax_withheld'],
                withholdingAtcCode:  $atcCode,
                withholdingRate:     $atcCode ? $withholding['rate'] : null,
                total:               Money::php($grandTotal),
            );

            foreach ($lines as $i => $row) {
                $lineSubtotal = bcmul((string) $row['quantity'], (string) $row['unit_price'], 4);
                $lineVat      = (string) ($row['vat_amount'] ?? '0');

                $bill->addLine(new VendorBillLine(
                    lineNo:               $i + 1,
                    description:          $row['description'],
                    quantity:             (string) $row['quantity'],
                    unitPrice:            Money::php((string) $row['unit_price']),
                    vatAmount:            Money::php($lineVat),
                    lineTotal:            Money::php(bcadd($lineSubtotal, $lineVat, 4)),
                    purchaseOrderLineId:  $row['purchase_order_line_id'] ?? null,
                    itemId:               $row['item_id'] ?? null,
                    expenseAccountId:     $row['expense_account_id'],
                    taxCodeId:            $row['tax_code_id'] ?? null,
                    projectId:            $row['project_id'] ?? null,
                ));
            }

            $this->bills->save($bill);

            // 4. Build + post the JV
            $jeLines = $this->buildJournalLines(
                $bill, $apAccountId, $vatInputAccountId, $withholdingPayableAccountId
            );

            $journalEntry = $this->createJournal->execute(
                companyId:        $companyId,
                documentSeriesId: $jvDocumentSeriesId,
                entryDate:        $billDate,
                lines:            $jeLines,
                source:           'purchase',
                sourceDocId:      $bill->id->value,
                sourceDocType:    'VendorBill',
                memo:             "Vendor Bill {$vendorInvoiceNo} from {$vendor->registeredName}",
                actorId:          $actorId,
            );

            $this->postJournal->execute(
                journalEntryId: $journalEntry->id->value,
                actorId:        $actorId,
            );

            // 5. Mark bill posted
            $bill->post(journalEntryId: $journalEntry->id->value);
            $this->bills->save($bill);

            // 6. Audit + event
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'vendorbill.posted',
                aggregate:   'VendorBill',
                aggregateId: $bill->id->value,
                payload: [
                    'vendor_invoice_no'    => $vendorInvoiceNo,
                    'vendor_id'            => $vendor->id->value,
                    'total'                => $bill->total->toPhp(),
                    'withholding_atc_code' => $bill->withholdingAtcCode,
                    'withholding_amount'   => $bill->withholdingAmount->toPhp(),
                    'journal_entry_id'     => $journalEntry->id->value,
                ],
            );

            $this->events->dispatch(new VendorBillPosted(
                vendorBillId:        $bill->id->value,
                companyId:           $companyId,
                vendorId:            $vendor->id->value,
                vendorInvoiceNo:     $vendorInvoiceNo,
                billDate:            $billDate,
                journalEntryId:      $journalEntry->id->value,
                total:               $bill->total->toPhp(),
                withholdingAmount:   $bill->withholdingAmount->toPhp(),
                withholdingAtcCode:  $bill->withholdingAtcCode,
                postedBy:            $actorId,
            ));

            return $bill;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildJournalLines(
        VendorBill $bill,
        string $apAccountId,
        string $vatInputAccountId,
        string $withholdingPayableAccountId,
    ): array {
        $lines = [];

        // DR — Expenses (per line, grouped by expense_account_id)
        $expenseByAccount = [];
        foreach ($bill->lines as $billLine) {
            $accountId = $billLine->expenseAccountId;
            if (! $accountId) {
                continue;
            }
            $base = bcsub($billLine->lineTotal->amount, $billLine->vatAmount->amount, 4);
            $expenseByAccount[$accountId] = bcadd($expenseByAccount[$accountId] ?? '0', $base, 4);
        }
        foreach ($expenseByAccount as $accountId => $amount) {
            $lines[] = ['account_id' => $accountId, 'debit' => $amount, 'php_amount' => $amount];
        }

        // DR — Input VAT
        if (! $bill->vatInput->isZero()) {
            $lines[] = [
                'account_id' => $vatInputAccountId,
                'debit'      => $bill->vatInput->toPhp(),
                'php_amount' => $bill->vatInput->toPhp(),
            ];
        }

        // CR — AP (vendor)
        $netPayable = $bill->netPayableToVendor();
        $lines[] = [
            'account_id' => $apAccountId,
            'credit'     => $netPayable->toPhp(),
            'php_amount' => $netPayable->negate()->amount,
        ];

        // CR — Withholding Tax Payable
        if (! $bill->withholdingAmount->isZero()) {
            $lines[] = [
                'account_id' => $withholdingPayableAccountId,
                'credit'     => $bill->withholdingAmount->toPhp(),
                'php_amount' => $bill->withholdingAmount->negate()->amount,
            ];
        }

        return $lines;
    }
}
