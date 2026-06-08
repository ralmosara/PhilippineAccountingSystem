<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Form2307ReceivedModel extends Model
{
    use HasUuids;

    protected $table = 'tax.form_2307_received';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'customer_id',
        'payor_tin', 'payor_registered_name', 'payor_branch_code', 'payor_address',
        'certificate_no', 'atc_code',
        'period_from', 'period_to',
        'income_payment', 'tax_withheld',
        'source_pdf_path', 'entry_method',
        'status', 'claimed_in_bir_form_id', 'rejection_reason',
        'journal_entry_id',
        'recorded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_from'    => 'date',
            'period_to'      => 'date',
            'income_payment' => 'decimal:2',
            'tax_withheld'   => 'decimal:2',
        ];
    }
}
