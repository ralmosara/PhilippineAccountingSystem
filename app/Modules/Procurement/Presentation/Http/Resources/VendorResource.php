<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorModel
 */
final class VendorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'vendor_no'                => $this->vendor_no,
            'registered_name'          => $this->registered_name,
            'trade_name'               => $this->trade_name,
            'tin'                      => $this->tin,
            'is_vat_registered'        => (bool) $this->is_vat_registered,
            'is_government_supplier'   => (bool) $this->is_government_supplier,
            'is_top_withholding_agent' => (bool) $this->is_top_withholding_agent,
            'default_atc_code'         => $this->default_atc_code,
            'default_withholding_rate' => $this->default_withholding_rate ? (string) $this->default_withholding_rate : null,
            'payment_terms_days'       => (int) $this->payment_terms_days,
            'email'                    => $this->email,
            'phone'                    => $this->phone,
            'is_active'                => (bool) $this->is_active,
            'created_at'               => $this->created_at?->toIso8601String(),
        ];
    }
}
