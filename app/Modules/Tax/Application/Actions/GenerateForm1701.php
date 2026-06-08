<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\AnnualIncomeTaxAggregatorContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Application\Queries\AggregateWithholdingCreditsForYear;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use App\Modules\Tax\Domain\Events\BirFormGenerated;
use App\Modules\Tax\Domain\Services\IndividualIncomeTaxCalculator;
use App\Modules\Tax\Domain\Services\OsdCalculator;
use App\Modules\Tax\Application\Actions\RecordOrConfirmOsdElection;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 1701 — Annual Income Tax Return for Individuals
 * (self-employed, professionals, mixed-income earners).
 *
 * Two computation paths driven by the `electFlat8Percent` flag:
 *   - **Graduated** (default): TRAIN annual brackets up to 35%
 *   - **8% Flat** (election): if gross sales ≤ ₱3M VAT threshold, taxpayer
 *     may elect 8% flat on gross in lieu of graduated + percentage tax
 *
 * Filing deadline: April 15 (calendar-year filers).
 */
final readonly class GenerateForm1701
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private AnnualIncomeTaxAggregatorContract $aggregator,
        private IndividualIncomeTaxCalculator $calculator,
        private PdfRendererContract $pdf,
        private AuditWriterContract $audit,
        private Dispatcher $events,
        private AggregateWithholdingCreditsForYear $creditAggregator,
        private Form2307ReceivedRepositoryContract $form2307Repo,
        private OsdCalculator $osd,
        private RecordOrConfirmOsdElection $osdElection,
    ) {
    }

    /**
     * @param  bool    $useOsd  elect 40% OSD on gross sales in lieu of itemized
     *                          deductions (RR 2-2010); cannot combine with 8% flat.
     */
    public function execute(
        string $companyId,
        int $year,
        string $actorId,
        bool   $electFlat8Percent       = false,
        string $personalExemption       = '0.00',          // legacy — TRAIN removed personal exemptions
        string $priorExcessCredits      = '0.00',
        string $creditableWtOverride    = '0.00',
        string $taxPaymentsToDate       = '0.00',
        bool   $useOsd                  = false,
    ): BirForm {
        $this->osd->assertNotCombinedWithFlat8Percent($useOsd, $electFlat8Percent);

        $period = FormPeriod::year($year);

        return DB::transaction(function () use (
            $companyId, $period, $year, $actorId,
            $electFlat8Percent, $personalExemption,
            $priorExcessCredits, $creditableWtOverride, $taxPaymentsToDate, $useOsd
        ) {
            $existing = $this->forms->findByPeriod($companyId, '1701', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            // Confirm / record the year's deduction-regime election against
            // tax.osd_elections. If Q1/Q2/Q3 1701Q filings already locked a
            // regime, this call asserts the annual matches; otherwise it
            // establishes the lock here (taxpayer with no Q-returns).
            $thisRegime = $electFlat8Percent ? 'flat_8pct' : ($useOsd ? 'osd' : 'itemized');
            $this->osdElection->execute(
                companyId:           $companyId,
                fiscalYear:          $year,
                taxpayerType:        'individual',
                regime:              $thisRegime,
                declaredInFormType:  '1701',
                declaredInQuarter:   null,
                declaredInBirFormId: null,
                actorId:             $actorId,
            );

            $totals = $this->aggregator->fiscalYearTotals($companyId, $period);

            // Pull the rich credit summary so we can lock contributing
            // 2307 certs against double-claiming in another filing.
            $creditSummary = $this->creditAggregator->execute($companyId, $year);
            $creditableWt  = bccomp($creditableWtOverride, '0', 2) > 0
                ? $creditableWtOverride
                : $creditSummary->totalTaxWithheld;

            // Auto-sum Q1+Q2+Q3 1701Q payments when no override supplied.
            if (bccomp($taxPaymentsToDate, '0', 2) === 0) {
                $taxPaymentsToDate = $this->aggregator->priorQuarterTaxPayments(
                    $companyId, '1701Q', $year, 4,
                );
            }

            $netSales    = bcsub($totals['gross_revenue'], $totals['sales_returns'], 2);
            $grossIncome = bcsub($netSales, $totals['cost_of_sales'], 2);

            // OSD election (RR 2-2010) for INDIVIDUALS:
            //   base = GROSS SALES (not gross income — that's the corporate rule)
            //   deduction = 40% of gross sales, replacing itemized deductions
            $deductionMethod     = $useOsd ? 'osd' : 'itemized';
            $allowableDeductions = $useOsd
                ? $this->osd->forIndividual($totals['gross_revenue'])
                : $totals['operating_expenses'];

            // Net Income from Operations recomputed when OSD is elected because
            // the aggregator's net_income_before_tax baked in itemized deductions.
            $netIncomeBeforeTax = $useOsd
                ? bcsub(
                    bcadd(
                        bcsub($grossIncome, $allowableDeductions, 2),
                        $totals['other_income'],
                        2,
                    ),
                    $totals['other_expenses'],
                    2,
                )
                : $totals['net_income_before_tax'];

            $taxableIncome = bcsub($netIncomeBeforeTax, $personalExemption, 2);

            $tax = $this->calculator->compute(
                taxableIncome:     $taxableIncome,
                grossSales:        $totals['gross_revenue'],
                electFlat8Percent: $electFlat8Percent,
            );

            $totalCredits = bcadd(bcadd($priorExcessCredits, $creditableWt, 2), $taxPaymentsToDate, 2);
            $taxStillDue  = bcsub($tax['tax_due'], $totalCredits, 2);

            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1701',
                period:    $period,
            );
            $form->lines = [];

            // Line numbers map to BIR 1701 (TRAIN-revised)
            $form->addLine(new BirFormLine('38',  'Gross Sales / Receipts / Revenues',         $totals['gross_revenue']));
            $form->addLine(new BirFormLine('39',  'Less: Sales Returns, Discounts',            $totals['sales_returns']));
            $form->addLine(new BirFormLine('40',  'Net Sales / Receipts',                       $netSales));
            $form->addLine(new BirFormLine('41',  'Less: Cost of Sales / Services',            $totals['cost_of_sales']));
            $form->addLine(new BirFormLine('42',  'Gross Income',                              $grossIncome));
            $form->addLine(new BirFormLine(
                '43',
                $useOsd
                    ? 'Less: Optional Standard Deduction (40% of Gross Sales, RR 2-2010)'
                    : 'Less: Allowable Deductions (Itemized)',
                $allowableDeductions,
            ));
            $form->addLine(new BirFormLine('43A', 'Other Income (non-operating)',              $totals['other_income']));
            $form->addLine(new BirFormLine('43B', 'Other Expenses (non-operating)',            $totals['other_expenses']));
            $form->addLine(new BirFormLine('44',  'Net Income from Operation',                 $netIncomeBeforeTax));
            $form->addLine(new BirFormLine('45',  'Personal/Additional Exemptions (legacy)',   $personalExemption));
            $form->addLine(new BirFormLine('46',  'Taxable Income',                            $taxableIncome));
            $form->addLine(new BirFormLine('47',  'Computation Method',                        $tax['method'] === 'flat_8pct' ? '8% Flat' : 'Graduated'));
            $form->addLine(new BirFormLine('48',  'Tax Due',                                   $tax['tax_due']));
            $form->addLine(new BirFormLine('49A', 'Less: Prior Year Excess Credits',           $priorExcessCredits));
            $form->addLine(new BirFormLine('49B', 'Less: Creditable WT',                       $creditableWt));
            $form->addLine(new BirFormLine('49C', 'Less: Quarterly Tax Payments',              $taxPaymentsToDate));
            $form->addLine(new BirFormLine('49D', 'Total Tax Credits / Payments',              $totalCredits));
            $form->addLine(new BirFormLine('50',  'Tax Still Due / (Overpayment)',             $taxStillDue));

            $form->markGenerated(taxDue: bccomp($taxStillDue, '0', 2) > 0 ? $taxStillDue : '0.00');
            $form->data = [
                'method'               => $tax['method'],
                'rate_pct'             => $tax['applied_rate_pct'],
                'totals'               => $totals,
                'deduction_method'     => $deductionMethod,
                'allowable_deductions' => $allowableDeductions,
                'taxable'              => $taxableIncome,
                'tax_due'              => $tax['tax_due'],
                'credits'              => [
                    'prior_excess'    => $priorExcessCredits,
                    'creditable_wt'   => $creditableWt,
                    'qtr_payments'    => $taxPaymentsToDate,
                ],
            ];

            $this->forms->save($form);

            // Lock the contributing 2307s to this filing (skipped when user
            // supplied an explicit creditableWtOverride — they own the lock).
            if (bccomp($creditableWtOverride, '0', 2) === 0
                && $creditSummary->claimableCertIds !== []) {
                $this->form2307Repo->markClaimed(
                    array_map(fn (string $id) => new Form2307ReceivedId($id), $creditSummary->claimableCertIds),
                    $form->id->value,
                );
            }

            $form->pdfPath = $this->pdf->render($form);
            $this->forms->save($form);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'birform.generated',
                aggregate:   'BirForm',
                aggregateId: $form->id->value,
                payload: [
                    'form_type'        => '1701',
                    'year'             => $year,
                    'method'           => $tax['method'],
                    'deduction_method' => $deductionMethod,
                    'tax_due'          => $tax['tax_due'],
                    'tax_still_due'    => $taxStillDue,
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1701',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $taxStillDue,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
