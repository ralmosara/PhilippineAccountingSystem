<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerModel extends Model
{
    use HasUuids;

    protected $table = 'sales.customers';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'customer_no', 'registered_name', 'trade_name', 'tin',
        'is_vat_registered', 'is_government', 'is_senior_citizen', 'is_pwd',
        'id_type', 'id_number', 'email', 'phone',
        'credit_limit', 'payment_terms_days', 'default_currency', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_vat_registered'   => 'boolean',
            'is_government'       => 'boolean',
            'is_senior_citizen'   => 'boolean',
            'is_pwd'              => 'boolean',
            'is_active'           => 'boolean',
            'credit_limit'        => 'decimal:2',
            'payment_terms_days'  => 'integer',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddressModel::class, 'customer_id');
    }
}
