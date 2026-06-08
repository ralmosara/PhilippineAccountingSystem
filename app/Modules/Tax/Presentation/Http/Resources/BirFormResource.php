<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel
 */
final class BirFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'form_type'     => $this->form_type,
            'period'        => [
                'from'    => $this->period_from?->format('Y-m-d'),
                'to'      => $this->period_to?->format('Y-m-d'),
                'year'    => (int) $this->year,
                'month'   => $this->month,
                'quarter' => $this->quarter,
            ],
            'status'        => $this->status,
            'tax_due'       => (string) $this->tax_due,
            'tax_paid'      => (string) $this->tax_paid,
            'generated_at'  => $this->generated_at?->toIso8601String(),
            'filed_at'      => $this->filed_at?->toIso8601String(),
            'bir_filing_ref'=> $this->bir_filing_ref,
            'pdf_path'      => $this->pdf_path,
            'xml_path'      => $this->xml_path,
            'dat_path'      => $this->dat_path,
            'lines'         => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'line_code'   => $l->line_code,
                'description' => $l->description,
                'amount'      => (string) $l->amount,
            ])),
            'alphalist_entries_count' => $this->whenLoaded('alphalistEntries', fn () => $this->alphalistEntries->count()),
        ];
    }
}
