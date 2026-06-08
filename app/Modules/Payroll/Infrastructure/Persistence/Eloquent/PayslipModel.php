<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PayslipModel extends Model
{
    use HasUuids;

    protected $table = 'payroll.payslips';

    /** @var array<int, string> */
    protected $fillable = [
        'payroll_run_id', 'employee_id',
        'gross_compensation', 'taxable_compensation', 'nontaxable_compensation',
        'sss_ee', 'sss_er', 'phic_ee', 'phic_er', 'hdmf_ee', 'hdmf_er',
        'withholding_tax', 'other_deductions', 'net_pay',
        'days_worked', 'hours_worked', 'leave_days_used',
        'overtime_pay', 'nightdiff_pay', 'holiday_pay',
        'generated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gross_compensation'      => 'decimal:2',
            'taxable_compensation'    => 'decimal:2',
            'nontaxable_compensation' => 'decimal:2',
            'sss_ee'  => 'decimal:2',   'sss_er'  => 'decimal:2',
            'phic_ee' => 'decimal:2',   'phic_er' => 'decimal:2',
            'hdmf_ee' => 'decimal:2',   'hdmf_er' => 'decimal:2',
            'withholding_tax'   => 'decimal:2',
            'other_deductions'  => 'decimal:2',
            'net_pay'           => 'decimal:2',
            'hours_worked'      => 'decimal:2',
            'overtime_pay'      => 'decimal:2',
            'nightdiff_pay'     => 'decimal:2',
            'holiday_pay'       => 'decimal:2',
            'generated_at'      => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLineModel::class, 'payslip_id')->orderBy('line_no');
    }
}
