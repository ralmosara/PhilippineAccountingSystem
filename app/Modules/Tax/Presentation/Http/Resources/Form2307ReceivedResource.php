<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Resources;

use App\Modules\Tax\Infrastructure\Persistence\Eloquent\Form2307ReceivedModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Form2307ReceivedModel
 */
final class Form2307ReceivedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'company_id'             => $this->company_id,
            'customer_id'            => $this->customer_id,
            'payor' => [
                'tin'             => $this->payor_tin,
                'registered_name' => $this->payor_registered_name,
                'branch_code'     => $this->payor_branch_code,
                'address'         => $this->payor_address,
            ],
            'certificate_no'         => $this->certificate_no,
            'atc_code'               => $this->atc_code,
            'period_from'            => $this->period_from?->format('Y-m-d'),
            'period_to'              => $this->period_to?->format('Y-m-d'),
            'income_payment'         => (string) $this->income_payment,
            'tax_withheld'           => (string) $this->tax_withheld,
            'source_pdf_path'        => $this->source_pdf_path,
            'entry_method'           => $this->entry_method,
            'status'                 => $this->status,
            'claimed_in_bir_form_id' => $this->claimed_in_bir_form_id,
            'rejection_reason'       => $this->rejection_reason,
            'journal_entry_id'       => $this->journal_entry_id,
            'recorded_by'            => $this->recorded_by,
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
