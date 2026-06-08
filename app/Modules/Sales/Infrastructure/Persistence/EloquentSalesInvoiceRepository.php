<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Sales\Application\Contracts\SalesInvoiceRepositoryContract;
use App\Modules\Sales\Domain\Entities\SalesInvoice;
use App\Modules\Sales\Domain\Entities\SalesInvoiceLine;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\SalesInvoiceLineModel;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\SalesInvoiceModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class EloquentSalesInvoiceRepository implements SalesInvoiceRepositoryContract
{
    public function findById(SalesInvoiceId $id): ?SalesInvoice
    {
        $model = SalesInvoiceModel::query()->with('lines')->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(SalesInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            SalesInvoiceModel::query()->updateOrInsert(
                ['id' => $invoice->id->value],
                [
                    'company_id'           => $invoice->companyId,
                    'customer_id'          => $invoice->customerId->value,
                    'document_series_id'   => $invoice->documentSeriesId,
                    'sequence_no'          => $this->extractSequenceNo($invoice->docNo),
                    'doc_no'               => $invoice->docNo,
                    'doc_kind'             => $invoice->docKind,
                    'invoice_date'         => $invoice->invoiceDate,
                    'due_date'             => $invoice->dueDate,
                    'currency'             => $invoice->currency,
                    'fx_rate'              => $invoice->fxRate,
                    'subtotal'             => $invoice->subtotal->toPhp(),
                    'vat_exempt_sales'     => $invoice->vatExemptSales->toPhp(),
                    'vat_zero_rated_sales' => $invoice->vatZeroRatedSales->toPhp(),
                    'vatable_sales'        => $invoice->vatableSales->toPhp(),
                    'vat_amount'           => $invoice->vatAmount->toPhp(),
                    'discount_amount'      => $invoice->discountAmount->toPhp(),
                    'senior_pwd_discount'  => $invoice->seniorPwdDiscount->toPhp(),
                    'withheld_vat'         => $invoice->withheldVat->toPhp(),
                    'total'                => $invoice->total->toPhp(),
                    'php_total'            => $invoice->total->toPhp(),
                    'posted_at'            => $invoice->postedAt,
                    'posted_by'            => $invoice->postedBy,
                    'journal_entry_id'     => $invoice->journalEntryId,
                    'voided_at'            => $invoice->voidedAt,
                    'void_reason'          => $invoice->voidReason,
                    'voided_by'            => $invoice->voidedBy,
                    'updated_at'           => now(),
                    'created_at'           => now(),
                ],
            );

            // Replace lines on every save (drafts only; posted invoices skip line saves
            // because they cannot be edited per BIR rules)
            if (! $invoice->isPosted() || $invoice->isVoided()) {
                SalesInvoiceLineModel::query()
                    ->where('sales_invoice_id', $invoice->id->value)
                    ->delete();
            }

            foreach ($invoice->lines as $line) {
                SalesInvoiceLineModel::query()->updateOrInsert(
                    [
                        'sales_invoice_id' => $invoice->id->value,
                        'line_no'          => $line->lineNo,
                    ],
                    [
                        'id'                 => Uuid::uuid4()->toString(),
                        'item_id'            => $line->itemId,
                        'description'        => $line->description,
                        'quantity'           => $line->quantity,
                        'unit_price'         => $line->unitPrice->amount,
                        'discount_pct'       => $line->discountPct,
                        'discount_amount'    => $line->discountAmount?->amount ?? 0,
                        'tax_code_id'        => $line->taxCodeId,
                        'vat_amount'         => $line->vatAmount->amount,
                        'line_total'         => $line->lineTotal->amount,
                        'revenue_account_id' => $line->revenueAccountId,
                        'project_id'         => $line->projectId,
                        'updated_at'         => now(),
                        'created_at'         => now(),
                    ],
                );
            }
        });
    }

    public function allocateDocNo(string $documentSeriesId): string
    {
        $row = DB::selectOne(
            'SELECT accounting.allocate_doc_no(?::uuid) AS sequence_no',
            [$documentSeriesId],
        );
        $sequence = (int) $row->sequence_no;

        $series = DB::selectOne(
            'SELECT prefix FROM accounting.document_series WHERE id = ?::uuid',
            [$documentSeriesId],
        );

        return $series->prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function toDomain(SalesInvoiceModel $m): SalesInvoice
    {
        $invoice = new SalesInvoice(
            id:                 new SalesInvoiceId($m->id),
            companyId:          $m->company_id,
            customerId:         new CustomerId($m->customer_id),
            documentSeriesId:   $m->document_series_id,
            docNo:              $m->doc_no,
            docKind:            $m->doc_kind,
            invoiceDate:        new DateTimeImmutable($m->invoice_date->toIso8601String()),
            dueDate:            $m->due_date ? new DateTimeImmutable($m->due_date->toIso8601String()) : null,
            currency:           $m->currency,
            fxRate:             (string) $m->fx_rate,
            subtotal:           Money::php((string) $m->subtotal),
            vatExemptSales:     Money::php((string) $m->vat_exempt_sales),
            vatZeroRatedSales:  Money::php((string) $m->vat_zero_rated_sales),
            vatableSales:       Money::php((string) $m->vatable_sales),
            vatAmount:          Money::php((string) $m->vat_amount),
            discountAmount:     Money::php((string) $m->discount_amount),
            seniorPwdDiscount:  Money::php((string) $m->senior_pwd_discount),
            withheldVat:        Money::php((string) $m->withheld_vat),
            total:              Money::php((string) $m->total),
        );

        $invoice->postedAt       = $m->posted_at ? new DateTimeImmutable($m->posted_at->toIso8601String()) : null;
        $invoice->postedBy       = $m->posted_by;
        $invoice->journalEntryId = $m->journal_entry_id;
        $invoice->voidedAt       = $m->voided_at ? new DateTimeImmutable($m->voided_at->toIso8601String()) : null;
        $invoice->voidReason     = $m->void_reason;
        $invoice->voidedBy       = $m->voided_by;

        foreach ($m->lines as $lineModel) {
            $invoice->lines[] = new SalesInvoiceLine(
                lineNo:           (int) $lineModel->line_no,
                description:      $lineModel->description,
                quantity:         (string) $lineModel->quantity,
                unitPrice:        Money::php((string) $lineModel->unit_price),
                vatAmount:        Money::php((string) $lineModel->vat_amount),
                lineTotal:        Money::php((string) $lineModel->line_total),
                itemId:           $lineModel->item_id,
                taxCodeId:        $lineModel->tax_code_id,
                revenueAccountId: $lineModel->revenue_account_id,
                projectId:        $lineModel->project_id,
                discountPct:      (string) $lineModel->discount_pct,
                discountAmount:   Money::php((string) $lineModel->discount_amount),
            );
        }

        return $invoice;
    }

    private function extractSequenceNo(string $docNo): int
    {
        if (preg_match('/(\d+)$/', $docNo, $m)) {
            return (int) $m[1];
        }
        throw new \InvalidArgumentException("Could not extract sequence from doc_no: {$docNo}");
    }
}
