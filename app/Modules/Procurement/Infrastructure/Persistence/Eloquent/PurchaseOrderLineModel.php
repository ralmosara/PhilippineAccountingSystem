<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PurchaseOrderLineModel extends Model
{
    use HasUuids;

    protected $table = 'procurement.purchase_order_lines';

    /** @var array<int, string> */
    protected $fillable = [
        'purchase_order_id', 'line_no', 'item_id', 'description',
        'quantity', 'received_quantity', 'billed_quantity',
        'unit_price', 'line_total',
        'expense_account_id', 'project_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'          => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'billed_quantity'   => 'decimal:4',
            'unit_price'        => 'decimal:4',
            'line_total'        => 'decimal:2',
        ];
    }
}
