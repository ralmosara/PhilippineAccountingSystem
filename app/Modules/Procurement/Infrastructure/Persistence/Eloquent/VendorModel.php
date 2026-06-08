<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VendorModel extends Model
{
    use HasUuids;

    protected $table = 'procurement.vendors';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'vendor_no', 'registered_name', 'trade_name', 'tin',
        'is_vat_registered', 'is_government_supplier', 'is_top_withholding_agent',
        'default_atc_code', 'default_withholding_rate',
        'payment_terms_days', 'default_currency',
        'contact_person', 'email', 'phone', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_vat_registered'        => 'boolean',
            'is_government_supplier'   => 'boolean',
            'is_top_withholding_agent' => 'boolean',
            'is_active'                => 'boolean',
            'default_withholding_rate' => 'decimal:4',
            'payment_terms_days'       => 'integer',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(VendorBillModel::class, 'vendor_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrderModel::class, 'vendor_id');
    }
}
