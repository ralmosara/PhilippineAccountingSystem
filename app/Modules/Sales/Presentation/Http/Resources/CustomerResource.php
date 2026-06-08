<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Sales\Infrastructure\Persistence\Eloquent\CustomerModel
 */
final class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'customer_no'         => $this->customer_no,
            'registered_name'     => $this->registered_name,
            'trade_name'          => $this->trade_name,
            'tin'                 => $this->tin,
            'is_vat_registered'   => (bool) $this->is_vat_registered,
            'is_government'       => (bool) $this->is_government,
            'is_senior_citizen'   => (bool) $this->is_senior_citizen,
            'is_pwd'              => (bool) $this->is_pwd,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'credit_limit'        => (string) $this->credit_limit,
            'payment_terms_days'  => (int) $this->payment_terms_days,
            'default_currency'    => $this->default_currency,
            'is_active'           => (bool) $this->is_active,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
