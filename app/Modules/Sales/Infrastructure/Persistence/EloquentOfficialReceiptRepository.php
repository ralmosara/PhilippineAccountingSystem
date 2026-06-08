<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Sales\Application\Contracts\OfficialReceiptRepositoryContract;
use App\Modules\Sales\Domain\Entities\OfficialReceipt;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use App\Modules\Sales\Domain\ValueObjects\OfficialReceiptId;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\OfficialReceiptModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentOfficialReceiptRepository implements OfficialReceiptRepositoryContract
{
    public function findById(OfficialReceiptId $id): ?OfficialReceipt
    {
        $model = OfficialReceiptModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(OfficialReceipt $receipt): void
    {
        OfficialReceiptModel::query()->updateOrInsert(
            ['id' => $receipt->id->value],
            [
                'company_id'         => $receipt->companyId,
                'sales_invoice_id'   => $receipt->salesInvoiceId?->value,
                'customer_id'        => $receipt->customerId->value,
                'document_series_id' => $receipt->documentSeriesId,
                'sequence_no'        => $this->extractSequenceNo($receipt->docNo),
                'doc_no'             => $receipt->docNo,
                'received_date'      => $receipt->receivedDate,
                'amount'             => $receipt->amount->toPhp(),
                'php_amount'         => $receipt->phpAmount->toPhp(),
                'payment_method'     => $receipt->paymentMethod,
                'reference_no'       => $receipt->referenceNo,
                'remarks'            => $receipt->remarks,
                'issued_by'          => $receipt->issuedBy,
                'updated_at'         => now(),
                'created_at'         => now(),
            ],
        );
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

    private function toDomain(OfficialReceiptModel $m): OfficialReceipt
    {
        return new OfficialReceipt(
            id:                new OfficialReceiptId($m->id),
            companyId:         $m->company_id,
            customerId:        new CustomerId($m->customer_id),
            salesInvoiceId:    $m->sales_invoice_id ? new SalesInvoiceId($m->sales_invoice_id) : null,
            documentSeriesId:  $m->document_series_id,
            docNo:             $m->doc_no,
            receivedDate:      new DateTimeImmutable($m->received_date->toIso8601String()),
            amount:            Money::php((string) $m->amount),
            phpAmount:         Money::php((string) $m->php_amount),
            paymentMethod:     $m->payment_method,
            referenceNo:       $m->reference_no,
            remarks:           $m->remarks,
            issuedBy:          $m->issued_by,
        );
    }

    private function extractSequenceNo(string $docNo): int
    {
        if (preg_match('/(\d+)$/', $docNo, $m)) {
            return (int) $m[1];
        }
        throw new \InvalidArgumentException("Could not extract sequence from doc_no: {$docNo}");
    }
}
