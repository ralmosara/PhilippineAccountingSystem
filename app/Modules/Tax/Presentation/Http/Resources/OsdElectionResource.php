<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Resources;

use App\Modules\Tax\Infrastructure\Persistence\Eloquent\OsdElectionModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OsdElectionModel
 */
final class OsdElectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'company_id'              => $this->company_id,
            'fiscal_year'             => (int) $this->fiscal_year,
            'taxpayer_type'           => $this->taxpayer_type,
            'regime'                  => $this->regime,
            'declared_in' => [
                'form_type'   => $this->declared_in_form_type,
                'quarter'     => $this->declared_in_quarter !== null ? (int) $this->declared_in_quarter : null,
                'bir_form_id' => $this->declared_in_bir_form_id,
            ],
            'locked_at'               => $this->locked_at?->toIso8601String(),
            'locked_by'               => $this->locked_by,
            'replaces_id'             => $this->replaces_id,
            'superseded_at'           => $this->superseded_at?->toIso8601String(),
            'supersede_reason'        => $this->supersede_reason,
            'is_active'               => $this->superseded_at === null,
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
