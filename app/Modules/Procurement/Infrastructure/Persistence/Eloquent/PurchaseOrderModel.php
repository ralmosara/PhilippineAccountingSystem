<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseOrderModel extends Model
{
    use HasUuids;

    protected $table = 'procurement.purchase_orders';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'vendor_id', 'document_series_id', 'sequence_no', 'po_no',
        'order_date', 'expected_delivery', 'currency', 'fx_rate',
        'subtotal', 'vat_amount', 'total',
        'status', 'approved_at', 'approved_by', 'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'order_date'        => 'date',
            'expected_delivery' => 'date',
            'approved_at'       => 'datetime',
            'subtotal'          => 'decimal:2',
            'vat_amount'        => 'decimal:2',
            'total'             => 'decimal:2',
            'fx_rate'           => 'decimal:8',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorModel::class, 'vendor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLineModel::class, 'purchase_order_id')
                    ->orderBy('line_no');
    }
}
