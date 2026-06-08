<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Payroll\Application\Contracts\PayrollRunRepositoryContract;
use App\Modules\Payroll\Domain\Entities\PayrollRun;
use App\Modules\Payroll\Domain\Entities\Payslip;
use App\Modules\Payroll\Domain\Entities\PayslipLine;
use App\Modules\Payroll\Domain\ValueObjects\PayrollRunId;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayrollRunModel;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayslipLineModel;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayslipModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class EloquentPayrollRunRepository implements PayrollRunRepositoryContract
{
    public function findById(PayrollRunId $id): ?PayrollRun
    {
        $model = PayrollRunModel::query()->with('payslips.lines')->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            PayrollRunModel::query()->updateOrInsert(
                ['id' => $run->id->value],
                [
                    'payroll_period_id' => $run->payrollPeriodId,
                    'run_no'            => $run->runNo,
                    'run_type'          => $run->runType,
                    'computed_at'       => $run->computedAt,
                    'approved_at'       => $run->approvedAt,
                    'approved_by'       => $run->approvedBy,
                    'paid_at'           => $run->paidAt,
                    'journal_entry_id'  => $run->journalEntryId,
                    'status'            => $run->status,
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ],
            );

            // Replace payslips on each save (only when not yet approved)
            if ($run->status !== 'approved' && $run->status !== 'paid') {
                PayslipLineModel::query()
                    ->whereIn('payslip_id', PayslipModel::query()->select('id')->where('payroll_run_id', $run->id->value))
                    ->delete();
                PayslipModel::query()->where('payroll_run_id', $run->id->value)->delete();
            }

            foreach ($run->payslips as $payslip) {
                PayslipModel::query()->updateOrInsert(
                    ['id' => $payslip->id],
                    [
                        'payroll_run_id'          => $run->id->value,
                        'employee_id'             => $payslip->employeeId,
                        'gross_compensation'      => $payslip->grossCompensation->toPhp(),
                        'taxable_compensation'    => $payslip->taxableCompensation->toPhp(),
                        'nontaxable_compensation' => $payslip->nontaxableCompensation->toPhp(),
                        'sss_ee'  => $payslip->sssEe->toPhp(),  'sss_er'  => $payslip->sssEr->toPhp(),
                        'phic_ee' => $payslip->phicEe->toPhp(), 'phic_er' => $payslip->phicEr->toPhp(),
                        'hdmf_ee' => $payslip->hdmfEe->toPhp(), 'hdmf_er' => $payslip->hdmfEr->toPhp(),
                        'withholding_tax'  => $payslip->withholdingTax->toPhp(),
                        'other_deductions' => $payslip->otherDeductions->toPhp(),
                        'net_pay'          => $payslip->netPay->toPhp(),
                        'days_worked'      => $payslip->daysWorked,
                        'hours_worked'     => $payslip->hoursWorked,
                        'overtime_pay'     => $payslip->overtimePay->toPhp(),
                        'nightdiff_pay'    => $payslip->nightdiffPay->toPhp(),
                        'holiday_pay'      => $payslip->holidayPay->toPhp(),
                        'generated_at'     => now(),
                        'updated_at'       => now(),
                        'created_at'       => now(),
                    ],
                );

                foreach ($payslip->lines as $line) {
                    PayslipLineModel::query()->updateOrInsert(
                        ['payslip_id' => $payslip->id, 'line_no' => $line->lineNo],
                        [
                            'id'           => Uuid::uuid4()->toString(),
                            'line_type'    => $line->lineType,
                            'code'         => $line->code,
                            'description'  => $line->description,
                            'quantity'     => $line->quantity,
                            'rate'         => $line->rate,
                            'amount'       => $line->amount->toPhp(),
                            'updated_at'   => now(),
                            'created_at'   => now(),
                        ],
                    );
                }
            }
        });
    }

    public function nextRunNo(string $companyId, string $runType, int $year, int $month): string
    {
        $prefix = $runType === '13th_month' ? '13M' : 'PR';
        $sequence = PayrollRunModel::query()
            ->where('run_no', 'like', sprintf('%s-%d-%02d-%%', $prefix, $year, $month))
            ->count() + 1;

        return sprintf('%s-%d-%02d-%03d', $prefix, $year, $month, $sequence);
    }

    private function toDomain(PayrollRunModel $m): PayrollRun
    {
        $run = new PayrollRun(
            id:              new PayrollRunId($m->id),
            payrollPeriodId: $m->payroll_period_id,
            runNo:           $m->run_no,
            runType:         $m->run_type,
        );
        $run->status         = $m->status;
        $run->computedAt     = $m->computed_at ? new DateTimeImmutable($m->computed_at->toIso8601String()) : null;
        $run->approvedAt     = $m->approved_at ? new DateTimeImmutable($m->approved_at->toIso8601String()) : null;
        $run->approvedBy     = $m->approved_by;
        $run->paidAt         = $m->paid_at     ? new DateTimeImmutable($m->paid_at->toIso8601String())     : null;
        $run->journalEntryId = $m->journal_entry_id;

        foreach ($m->payslips as $pm) {
            $payslip = new Payslip(
                id:                     $pm->id,
                payrollRunId:           $pm->payroll_run_id,
                employeeId:             $pm->employee_id,
                grossCompensation:      Money::php((string) $pm->gross_compensation),
                taxableCompensation:    Money::php((string) $pm->taxable_compensation),
                nontaxableCompensation: Money::php((string) $pm->nontaxable_compensation),
                sssEe:                  Money::php((string) $pm->sss_ee),
                sssEr:                  Money::php((string) $pm->sss_er),
                phicEe:                 Money::php((string) $pm->phic_ee),
                phicEr:                 Money::php((string) $pm->phic_er),
                hdmfEe:                 Money::php((string) $pm->hdmf_ee),
                hdmfEr:                 Money::php((string) $pm->hdmf_er),
                withholdingTax:         Money::php((string) $pm->withholding_tax),
                otherDeductions:        Money::php((string) $pm->other_deductions),
                netPay:                 Money::php((string) $pm->net_pay),
                daysWorked:             (int) $pm->days_worked,
                hoursWorked:            (string) $pm->hours_worked,
                overtimePay:            Money::php((string) $pm->overtime_pay),
                nightdiffPay:           Money::php((string) $pm->nightdiff_pay),
                holidayPay:             Money::php((string) $pm->holiday_pay),
            );

            foreach ($pm->lines as $lm) {
                $payslip->addLine(new PayslipLine(
                    lineNo:      (int) $lm->line_no,
                    lineType:    $lm->line_type,
                    code:        $lm->code,
                    description: $lm->description,
                    amount:      Money::php((string) $lm->amount),
                    quantity:    $lm->quantity ? (string) $lm->quantity : null,
                    rate:        $lm->rate ? (string) $lm->rate : null,
                ));
            }

            $run->addPayslip($payslip);
        }

        return $run;
    }
}
