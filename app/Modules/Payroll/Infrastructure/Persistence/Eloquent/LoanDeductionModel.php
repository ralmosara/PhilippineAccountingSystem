<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class LoanDeductionModel extends Model
{
    use HasUuids;

    protected $table = 'payroll.loan_deductions';

    /** @var array<int, string> */
    protected $fillable = [
        'id',
        'company_id',
        'employee_id',
        'loan_type',
        'loan_reference',
        'original_amount',
        'outstanding_balance',
        'monthly_amortization',
        'started_on',
        'ends_on',
        'is_active',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'loan_type'  => 'string',
            'is_active'  => 'boolean',
            'started_on' => 'date:Y-m-d',
            'ends_on'    => 'date:Y-m-d',
        ];
    }
}
