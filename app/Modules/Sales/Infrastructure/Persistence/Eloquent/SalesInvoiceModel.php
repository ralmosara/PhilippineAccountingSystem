<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SalesInvoiceModel extends Model
{
    use HasUuids;

    protected $table = 'sales.sales_invoices';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'customer_id', 'sales_order_id', 'document_series_id',
        'sequence_no', 'doc_no', 'doc_kind', 'invoice_date', 'due_date',
        'currency', 'fx_rate',
        'subtotal', 'vat_exempt_sales', 'vat_zero_rated_sales',
        'vatable_sales', 'vat_amount', 'discount_amount',
        'senior_pwd_discount', 'withheld_vat', 'total', 'php_total',
        'posted_at', 'posted_by', 'journal_entry_id',
        'voided_at', 'void_reason', 'voided_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invoice_date'         => 'date',
            'due_date'             => 'date',
            'posted_at'            => 'datetime',
            'voided_at'            => 'datetime',
            'subtotal'             => 'decimal:2',
            'vat_exempt_sales'     => 'decimal:2',
            'vat_zero_rated_sales' => 'decimal:2',
            'vatable_sales'        => 'decimal:2',
            'vat_amount'           => 'decimal:2',
            'discount_amount'      => 'decimal:2',
            'senior_pwd_discount'  => 'decimal:2',
            'withheld_vat'         => 'decimal:2',
            'total'                => 'decimal:2',
            'php_total'            => 'decimal:2',
            'fx_rate'              => 'decimal:8',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLineModel::class, 'sales_invoice_id')
                    ->orderBy('line_no');
    }
}
