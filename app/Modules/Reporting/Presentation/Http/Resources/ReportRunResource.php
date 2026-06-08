<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Reporting\Infrastructure\Persistence\Eloquent\ReportRunModel
 */
final class ReportRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'report_type'  => $this->report_type,
            'period_from'  => $this->period_from?->format('Y-m-d'),
            'period_to'    => $this->period_to?->format('Y-m-d'),
            'as_of_date'   => $this->as_of_date?->format('Y-m-d'),
            'pdf_path'     => $this->pdf_path,
            'csv_path'     => $this->csv_path,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'generated_by' => $this->generated_by,
            'payload'      => $request->boolean('include_payload') ? $this->payload : null,
        ];
    }
}
