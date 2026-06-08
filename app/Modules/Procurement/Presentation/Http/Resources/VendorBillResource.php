<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorBillModel
 */
final class VendorBillResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'vendor_id'             => $this->vendor_id,
            'vendor'                => $this->whenLoaded('vendor', fn () => new VendorResource($this->vendor)),
            'purchase_order_id'     => $this->purchase_order_id,
            'vendor_invoice_no'     => $this->vendor_invoice_no,
            'vendor_invoice_date'   => $this->vendor_invoice_date?->format('Y-m-d'),
            'bill_date'             => $this->bill_date?->format('Y-m-d'),
            'due_date'              => $this->due_date?->format('Y-m-d'),
            'currency'              => $this->currency,
            'totals'                => [
                'subtotal'             => (string) $this->subtotal,
                'vat_input'            => (string) $this->vat_input,
                'vat_input_deferred'   => (string) $this->vat_input_deferred,
                'withholding_amount'   => (string) $this->withholding_amount,
                'withholding_atc_code' => $this->withholding_atc_code,
                'withholding_rate'     => $this->withholding_rate ? (string) $this->withholding_rate : null,
                'total'                => (string) $this->total,
                'php_total'            => (string) $this->php_total,
                'net_payable'          => bcsub((string) $this->total, (string) $this->withholding_amount, 2),
            ],
            'is_posted'             => $this->posted_at !== null,
            'posted_at'             => $this->posted_at?->toIso8601String(),
            'is_voided'             => $this->voided_at !== null,
            'match_status'          => $this->match_status,
            'three_way_matched_at'  => $this->three_way_matched_at?->toIso8601String(),
            'journal_entry_id'      => $this->journal_entry_id,
            'lines'                 => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'line_no'             => (int) $l->line_no,
                'description'         => $l->description,
                'quantity'            => (string) $l->quantity,
                'unit_price'          => (string) $l->unit_price,
                'vat_amount'          => (string) $l->vat_amount,
                'line_total'          => (string) $l->line_total,
                'expense_account_id'  => $l->expense_account_id,
                'item_id'             => $l->item_id,
                'project_id'          => $l->project_id,
            ])),
        ];
    }
}
