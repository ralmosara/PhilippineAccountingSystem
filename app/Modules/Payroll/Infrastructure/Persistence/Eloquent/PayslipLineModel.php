<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PayslipLineModel extends Model
{
    use HasUuids;

    protected $table = 'payroll.payslip_lines';

    /** @var array<int, string> */
    protected $fillable = [
        'payslip_id', 'line_no', 'line_type', 'code', 'description',
        'quantity', 'rate', 'amount',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate'     => 'decimal:4',
            'amount'   => 'decimal:2',
        ];
    }
}
