<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Sales\Infrastructure\Persistence\Eloquent\OfficialReceiptModel
 */
final class OfficialReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'doc_no'            => $this->doc_no,
            'customer_id'       => $this->customer_id,
            'sales_invoice_id'  => $this->sales_invoice_id,
            'received_date'     => $this->received_date?->format('Y-m-d'),
            'amount'            => (string) $this->amount,
            'currency'          => $this->currency,
            'fx_rate'           => (string) $this->fx_rate,
            'php_amount'        => (string) $this->php_amount,
            'payment_method'    => $this->payment_method,
            'reference_no'      => $this->reference_no,
            'remarks'           => $this->remarks,
            'is_voided'         => $this->voided_at !== null,
            'voided_at'         => $this->voided_at?->toIso8601String(),
            'void_reason'       => $this->void_reason,
            'journal_entry_id'  => $this->journal_entry_id,
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
