<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PayrollRunModel extends Model
{
    use HasUuids;

    protected $table = 'payroll.payroll_runs';

    /** @var array<int, string> */
    protected $fillable = [
        'payroll_period_id', 'run_no', 'run_type',
        'computed_at', 'computed_by',
        'approved_at', 'approved_by',
        'paid_at', 'journal_entry_id', 'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at'     => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayslipModel::class, 'payroll_run_id');
    }
}
