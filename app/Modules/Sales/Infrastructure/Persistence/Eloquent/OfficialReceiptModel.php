<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OfficialReceiptModel extends Model
{
    use HasUuids;

    protected $table = 'sales.official_receipts';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'sales_invoice_id', 'customer_id',
        'document_series_id', 'sequence_no', 'doc_no',
        'received_date', 'amount', 'currency', 'fx_rate', 'php_amount',
        'payment_method', 'reference_no', 'remarks',
        'voided_at', 'void_reason', 'voided_by', 'issued_by', 'journal_entry_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'amount'        => 'decimal:2',
            'fx_rate'       => 'decimal:8',
            'php_amount'    => 'decimal:2',
            'voided_at'     => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceModel::class, 'sales_invoice_id');
    }
}
