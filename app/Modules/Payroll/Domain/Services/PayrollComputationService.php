<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Services;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Payroll\Domain\Entities\Payslip;
use App\Modules\Payroll\Domain\Entities\PayslipLine;
use Ramsey\Uuid\Uuid;

/**
 * Orchestrates a single payslip computation:
 *   1. Compute gross compensation
 *   2. Compute statutory deductions (SSS / PhilHealth / Pag-IBIG — all on monthly basic)
 *   3. Compute taxable compensation = gross − statutory_ee − nontaxable
 *   4. Compute withholding tax (TRAIN Law brackets per payroll frequency)
 *   5. Compute net pay = gross − all deductions
 *
 * Inputs come pre-shaped from the Application layer; this service is
 * pure-domain (no DB, no events, no audit calls).
 */
final readonly class PayrollComputationService
{
    public function __construct(
        private StatutoryDeductionCalculator $statutory,
        private TrainLawWithholdingCalculator $withholding,
    ) {
    }

    /**
     * @param  array{
     *     payroll_run_id: string,
     *     employee_id: string,
     *     monthly_basic: string,
     *     taxable_allowances: string,
     *     nontaxable_allowances: string,
     *     is_minimum_wage_earner: bool,
     *     days_worked: int,
     *     hours_worked: string,
     *     overtime_pay: string,
     *     nightdiff_pay: string,
     *     holiday_pay: string,
     *     other_deductions: string,
     * }  $input
     * @param  array{
     *     sss_brackets: list<array{msc_floor: string, msc_ceiling: string, ee_amount: string, er_amount: string}>,
     *     philhealth: array{premium_rate: string, salary_floor: string, salary_ceiling: string},
     *     pagibig: array{ee_rate_low: string, ee_rate_high: string, er_rate: string, low_threshold: string, salary_cap: string},
     *     bir_brackets: list<array{floor: string, ceiling: string|null, base_tax: string, rate: string}>,
     * }  $rates
     */
    public function compute(array $input, array $rates): Payslip
    {
        $monthlyBasic = Money::php($input['monthly_basic']);
        $taxableAllowances = Money::php($input['taxable_allowances']);
        $nontaxableAllowances = Money::php($input['nontaxable_allowances']);
        $overtime  = Money::php($input['overtime_pay']);
        $nightdiff = Money::php($input['nightdiff_pay']);
        $holiday   = Money::php($input['holiday_pay']);

        // 1. Gross — basic + all earnings (taxable + nontaxable + premium pay)
        $gross = $monthlyBasic
            ->add($taxableAllowances)
            ->add($nontaxableAllowances)
            ->add($overtime)
            ->add($nightdiff)
            ->add($holiday);

        // 2. Statutory — on basic monthly only
        $sss        = $this->statutory->computeSss($monthlyBasic, $rates['sss_brackets']);
        $philhealth = $this->statutory->computePhilhealth($monthlyBasic, $rates['philhealth']);
        $pagibig    = $this->statutory->computePagibig($monthlyBasic, $rates['pagibig']);

        $statutoryEeTotal = $sss['ee']->add($philhealth['ee'])->add($pagibig['ee']);

        // 3. Taxable compensation = basic + taxable allowances + premium pay − statutory_ee
        //    (nontaxable allowances under de minimis caps are excluded)
        $taxableComp = $monthlyBasic
            ->add($taxableAllowances)
            ->add($overtime)
            ->add($nightdiff)
            ->add($holiday)
            ->subtract($statutoryEeTotal);

        // 4. Withholding tax — exempt for MWE
        $withheld = $input['is_minimum_wage_earner']
            ? Money::zero()
            : $this->withholding->compute($taxableComp, $rates['bir_brackets']);

        $otherDeductions = Money::php($input['other_deductions']);

        // 5. Build payslip
        $payslip = new Payslip(
            id:                     Uuid::uuid4()->toString(),
            payrollRunId:           $input['payroll_run_id'],
            employeeId:             $input['employee_id'],
            grossCompensation:      $gross,
            taxableCompensation:    $taxableComp,
            nontaxableCompensation: $nontaxableAllowances,
            sssEe:                  $sss['ee'],
            sssEr:                  $sss['er'],
            phicEe:                 $philhealth['ee'],
            phicEr:                 $philhealth['er'],
            hdmfEe:                 $pagibig['ee'],
            hdmfEr:                 $pagibig['er'],
            withholdingTax:         $withheld,
            otherDeductions:        $otherDeductions,
            daysWorked:             $input['days_worked'],
            hoursWorked:            $input['hours_worked'],
            overtimePay:            $overtime,
            nightdiffPay:           $nightdiff,
            holidayPay:             $holiday,
        );

        // Lines (line_type and code follow consistent conventions)
        $payslip->addLine(new PayslipLine(1, 'earning',    'BASIC',      'Basic pay',                $monthlyBasic));
        if (! $taxableAllowances->isZero()) {
            $payslip->addLine(new PayslipLine(2, 'allowance', 'TAXABLE',   'Taxable allowances',       $taxableAllowances));
        }
        if (! $nontaxableAllowances->isZero()) {
            $payslip->addLine(new PayslipLine(3, 'allowance', 'NONTAX',    'Non-taxable allowances',   $nontaxableAllowances));
        }
        if (! $overtime->isZero()) {
            $payslip->addLine(new PayslipLine(4, 'earning',   'OT',        'Overtime pay',             $overtime));
        }
        if (! $nightdiff->isZero()) {
            $payslip->addLine(new PayslipLine(5, 'earning',   'NIGHTDIFF', 'Night differential',       $nightdiff));
        }
        if (! $holiday->isZero()) {
            $payslip->addLine(new PayslipLine(6, 'earning',   'HOLIDAY',   'Holiday pay',              $holiday));
        }
        $payslip->addLine(new PayslipLine(10, 'statutory', 'SSS',       'SSS contribution',         $sss['ee']->negate()));
        $payslip->addLine(new PayslipLine(11, 'statutory', 'PHIC',      'PhilHealth contribution',  $philhealth['ee']->negate()));
        $payslip->addLine(new PayslipLine(12, 'statutory', 'HDMF',      'Pag-IBIG contribution',    $pagibig['ee']->negate()));
        if (! $withheld->isZero()) {
            $payslip->addLine(new PayslipLine(13, 'tax',       'WT-COMP',   'Withholding tax (TRAIN)',  $withheld->negate()));
        }
        if (! $otherDeductions->isZero()) {
            $payslip->addLine(new PayslipLine(20, 'deduction', 'OTHER',     'Other deductions',         $otherDeductions->negate()));
        }

        $payslip->recalcNetPay();

        return $payslip;
    }
}
