<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Sales\Infrastructure\Persistence\Eloquent\SalesInvoiceModel
 */
final class SalesInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'doc_no'               => $this->doc_no,
            'doc_kind'             => $this->doc_kind,
            'customer_id'          => $this->customer_id,
            'customer'             => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'invoice_date'         => $this->invoice_date?->format('Y-m-d'),
            'due_date'             => $this->due_date?->format('Y-m-d'),
            'currency'             => $this->currency,
            'fx_rate'              => (string) $this->fx_rate,
            'totals'               => [
                'vatable_sales'        => (string) $this->vatable_sales,
                'vat_zero_rated_sales' => (string) $this->vat_zero_rated_sales,
                'vat_exempt_sales'     => (string) $this->vat_exempt_sales,
                'vat_amount'           => (string) $this->vat_amount,
                'discount_amount'      => (string) $this->discount_amount,
                'senior_pwd_discount'  => (string) $this->senior_pwd_discount,
                'withheld_vat'         => (string) $this->withheld_vat,
                'total'                => (string) $this->total,
                'php_total'            => (string) $this->php_total,
            ],
            'is_posted'            => $this->posted_at !== null,
            'posted_at'            => $this->posted_at?->toIso8601String(),
            'is_voided'            => $this->voided_at !== null,
            'voided_at'            => $this->voided_at?->toIso8601String(),
            'void_reason'          => $this->void_reason,
            'journal_entry_id'     => $this->journal_entry_id,
            'lines'                => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'line_no'        => (int) $l->line_no,
                'description'    => $l->description,
                'quantity'       => (string) $l->quantity,
                'unit_price'     => (string) $l->unit_price,
                'discount_pct'   => (string) $l->discount_pct,
                'vat_amount'     => (string) $l->vat_amount,
                'line_total'     => (string) $l->line_total,
                'tax_code_id'    => $l->tax_code_id,
                'item_id'        => $l->item_id,
                'project_id'     => $l->project_id,
            ])),
        ];
    }
}
