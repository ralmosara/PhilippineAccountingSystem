<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesInvoiceLineModel extends Model
{
    use HasUuids;

    protected $table = 'sales.sales_invoice_lines';

    /** @var array<int, string> */
    protected $fillable = [
        'sales_invoice_id', 'line_no', 'item_id', 'description',
        'quantity', 'unit_price', 'discount_pct', 'discount_amount',
        'tax_code_id', 'vat_amount', 'line_total',
        'revenue_account_id', 'project_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'        => 'decimal:4',
            'unit_price'      => 'decimal:4',
            'discount_pct'    => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'vat_amount'      => 'decimal:2',
            'line_total'      => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceModel::class, 'sales_invoice_id');
    }
}
