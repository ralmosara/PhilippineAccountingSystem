<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VendorBillModel extends Model
{
    use HasUuids;

    protected $table = 'procurement.vendor_bills';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'vendor_id', 'purchase_order_id',
        'vendor_invoice_no', 'vendor_invoice_date', 'bill_date', 'due_date',
        'currency', 'fx_rate',
        'subtotal', 'vat_input', 'vat_input_deferred',
        'withholding_amount', 'withholding_atc_code', 'withholding_rate',
        'total', 'php_total',
        'posted_at', 'journal_entry_id',
        'three_way_matched_at', 'match_status',
        'voided_at', 'void_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'vendor_invoice_date'  => 'date',
            'bill_date'            => 'date',
            'due_date'             => 'date',
            'posted_at'            => 'datetime',
            'three_way_matched_at' => 'datetime',
            'voided_at'            => 'datetime',
            'subtotal'             => 'decimal:2',
            'vat_input'            => 'decimal:2',
            'vat_input_deferred'   => 'decimal:2',
            'withholding_amount'   => 'decimal:2',
            'withholding_rate'     => 'decimal:4',
            'total'                => 'decimal:2',
            'php_total'            => 'decimal:2',
            'fx_rate'              => 'decimal:8',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorModel::class, 'vendor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorBillLineModel::class, 'vendor_bill_id')
                    ->orderBy('line_no');
    }
}
