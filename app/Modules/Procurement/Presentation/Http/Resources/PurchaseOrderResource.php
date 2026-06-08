<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Infrastructure\Persistence\Eloquent\PurchaseOrderModel
 */
final class PurchaseOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'po_no'              => $this->po_no,
            'vendor_id'          => $this->vendor_id,
            'order_date'         => $this->order_date?->format('Y-m-d'),
            'expected_delivery'  => $this->expected_delivery?->format('Y-m-d'),
            'currency'           => $this->currency,
            'subtotal'           => (string) $this->subtotal,
            'vat_amount'         => (string) $this->vat_amount,
            'total'              => (string) $this->total,
            'status'             => $this->status,
            'approved_at'        => $this->approved_at?->toIso8601String(),
            'remarks'            => $this->remarks,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'line_no'           => (int) $l->line_no,
                'description'       => $l->description,
                'quantity'          => (string) $l->quantity,
                'received_quantity' => (string) $l->received_quantity,
                'billed_quantity'   => (string) $l->billed_quantity,
                'unit_price'        => (string) $l->unit_price,
                'line_total'        => (string) $l->line_total,
                'item_id'           => $l->item_id,
                'expense_account_id'=> $l->expense_account_id,
            ])),
        ];
    }
}
