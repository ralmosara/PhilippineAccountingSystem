<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Procurement\Application\Contracts\VendorBillRepositoryContract;
use App\Modules\Procurement\Domain\Entities\VendorBill;
use App\Modules\Procurement\Domain\Entities\VendorBillLine;
use App\Modules\Procurement\Domain\ValueObjects\VendorBillId;
use App\Modules\Procurement\Domain\ValueObjects\VendorId;
use App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorBillLineModel;
use App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorBillModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class EloquentVendorBillRepository implements VendorBillRepositoryContract
{
    public function findById(VendorBillId $id): ?VendorBill
    {
        $model = VendorBillModel::query()->with('lines')->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(VendorBill $bill): void
    {
        DB::transaction(function () use ($bill) {
            VendorBillModel::query()->updateOrInsert(
                ['id' => $bill->id->value],
                [
                    'company_id'           => $bill->companyId,
                    'vendor_id'            => $bill->vendorId->value,
                    'purchase_order_id'    => $bill->purchaseOrderId,
                    'vendor_invoice_no'    => $bill->vendorInvoiceNo,
                    'vendor_invoice_date'  => $bill->vendorInvoiceDate,
                    'bill_date'            => $bill->billDate,
                    'due_date'             => $bill->dueDate,
                    'currency'             => $bill->currency,
                    'fx_rate'              => $bill->fxRate,
                    'subtotal'             => $bill->subtotal->toPhp(),
                    'vat_input'            => $bill->vatInput->toPhp(),
                    'vat_input_deferred'   => $bill->vatInputDeferred->toPhp(),
                    'withholding_amount'   => $bill->withholdingAmount->toPhp(),
                    'withholding_atc_code' => $bill->withholdingAtcCode,
                    'withholding_rate'     => $bill->withholdingRate,
                    'total'                => $bill->total->toPhp(),
                    'php_total'            => $bill->total->toPhp(),
                    'posted_at'            => $bill->postedAt,
                    'journal_entry_id'     => $bill->journalEntryId,
                    'match_status'         => $bill->matchStatus,
                    'three_way_matched_at' => $bill->threeWayMatchedAt,
                    'voided_at'            => $bill->voidedAt,
                    'updated_at'           => now(),
                    'created_at'           => now(),
                ],
            );

            if (! $bill->isPosted()) {
                VendorBillLineModel::query()
                    ->where('vendor_bill_id', $bill->id->value)
                    ->delete();
            }

            foreach ($bill->lines as $line) {
                VendorBillLineModel::query()->updateOrInsert(
                    ['vendor_bill_id' => $bill->id->value, 'line_no' => $line->lineNo],
                    [
                        'id'                     => Uuid::uuid4()->toString(),
                        'purchase_order_line_id' => $line->purchaseOrderLineId,
                        'item_id'                => $line->itemId,
                        'description'            => $line->description,
                        'quantity'               => $line->quantity,
                        'unit_price'             => $line->unitPrice->amount,
                        'vat_amount'             => $line->vatAmount->amount,
                        'line_total'             => $line->lineTotal->amount,
                        'expense_account_id'     => $line->expenseAccountId,
                        'tax_code_id'            => $line->taxCodeId,
                        'project_id'             => $line->projectId,
                        'updated_at'             => now(),
                        'created_at'             => now(),
                    ],
                );
            }
        });
    }

    private function toDomain(VendorBillModel $m): VendorBill
    {
        $bill = new VendorBill(
            id:                  new VendorBillId($m->id),
            companyId:           $m->company_id,
            vendorId:            new VendorId($m->vendor_id),
            purchaseOrderId:     $m->purchase_order_id,
            vendorInvoiceNo:     $m->vendor_invoice_no,
            vendorInvoiceDate:   new DateTimeImmutable($m->vendor_invoice_date->toIso8601String()),
            billDate:            new DateTimeImmutable($m->bill_date->toIso8601String()),
            dueDate:             $m->due_date ? new DateTimeImmutable($m->due_date->toIso8601String()) : null,
            currency:            $m->currency,
            fxRate:              (string) $m->fx_rate,
            subtotal:            Money::php((string) $m->subtotal),
            vatInput:            Money::php((string) $m->vat_input),
            vatInputDeferred:    Money::php((string) $m->vat_input_deferred),
            withholdingAmount:   Money::php((string) $m->withholding_amount),
            withholdingAtcCode:  $m->withholding_atc_code,
            withholdingRate:     $m->withholding_rate ? (string) $m->withholding_rate : null,
            total:               Money::php((string) $m->total),
        );

        $bill->postedAt           = $m->posted_at ? new DateTimeImmutable($m->posted_at->toIso8601String()) : null;
        $bill->journalEntryId     = $m->journal_entry_id;
        $bill->matchStatus        = $m->match_status ?? 'unmatched';
        $bill->threeWayMatchedAt  = $m->three_way_matched_at ? new DateTimeImmutable($m->three_way_matched_at->toIso8601String()) : null;
        $bill->voidedAt           = $m->voided_at ? new DateTimeImmutable($m->voided_at->toIso8601String()) : null;

        foreach ($m->lines as $lineModel) {
            $bill->lines[] = new VendorBillLine(
                lineNo:               (int) $lineModel->line_no,
                description:          $lineModel->description,
                quantity:             (string) $lineModel->quantity,
                unitPrice:            Money::php((string) $lineModel->unit_price),
                vatAmount:            Money::php((string) $lineModel->vat_amount),
                lineTotal:            Money::php((string) $lineModel->line_total),
                purchaseOrderLineId:  $lineModel->purchase_order_line_id,
                itemId:               $lineModel->item_id,
                expenseAccountId:     $lineModel->expense_account_id,
                taxCodeId:            $lineModel->tax_code_id,
                projectId:            $lineModel->project_id,
            );
        }

        return $bill;
    }
}
