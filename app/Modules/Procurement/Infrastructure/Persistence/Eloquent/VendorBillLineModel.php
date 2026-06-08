<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class VendorBillLineModel extends Model
{
    use HasUuids;

    protected $table = 'procurement.vendor_bill_lines';

    /** @var array<int, string> */
    protected $fillable = [
        'vendor_bill_id', 'line_no', 'purchase_order_line_id', 'item_id',
        'description', 'quantity', 'unit_price', 'vat_amount', 'line_total',
        'expense_account_id', 'tax_code_id', 'project_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_price' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
