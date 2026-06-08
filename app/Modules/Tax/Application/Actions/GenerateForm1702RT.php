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
use App\Modules\Tax\Application\Actions\RecordOrConfirmOsdElection;
use App\Modules\Tax\Domain\Services\CorporateIncomeTaxCalculator;
use App\Modules\Tax\Domain\Services\OsdCalculator;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 1702-RT (Annual Income Tax Return for Corporations
 * Subject to the Regular Rate).
 *
 * Flow:
 *   1. Aggregate fiscal-year revenue / COGS / OpEx / other / accrued tax
 *   2. Compute taxable income (net income before tax, less itemized deductions)
 *   3. CREATE Act rate: 20% if MSME (≤₱5M taxable AND ≤₱100M total assets),
 *      else 25%; MCIT 2% comparison
 *   4. Subtract prior excess credits + creditable WT to get tax payable
 *   5. Persist BirForm + lines, render PDF
 *
 * Filing deadline: April 15 (calendar-year filers) or 15th day of 4th
 * month following fiscal year-end (non-calendar filers).
 */
final readonly class GenerateForm1702RT
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private AnnualIncomeTaxAggregatorContract $aggregator,
        private CorporateIncomeTaxCalculator $calculator,
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
     * @param  string  $priorExcessCredits        carried over from prior year
     * @param  string  $creditableWtOverride      override the aggregator's 0.00 with the user's known total
     * @param  string  $taxPaymentsToDate         quarterly ITR payments made during the year
     * @param  bool    $useOsd                    elect 40% OSD in lieu of itemized deductions (RR 2-2010)
     */
    public function execute(
        string $companyId,
        int $year,
        string $actorId,
        string $priorExcessCredits   = '0.00',
        string $creditableWtOverride = '0.00',
        string $taxPaymentsToDate    = '0.00',
        bool   $useOsd               = false,
    ): BirForm {
        $period = FormPeriod::year($year);

        return DB::transaction(function () use (
            $companyId, $period, $year, $actorId,
            $priorExcessCredits, $creditableWtOverride, $taxPaymentsToDate, $useOsd
        ) {
            $existing = $this->forms->findByPeriod($companyId, '1702RT', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            // Confirm / record the corporate deduction-regime election —
            // throws OsdElectionMismatchException if 1702Q Q-returns locked
            // a different regime earlier in the year (RR 2-2010 § 7).
            $thisRegime = $useOsd ? 'osd' : 'itemized';
            $this->osdElection->execute(
                companyId:           $companyId,
                fiscalYear:          $year,
                taxpayerType:        'corporate',
                regime:              $thisRegime,
                declaredInFormType:  '1702RT',
                declaredInQuarter:   null,
                declaredInBirFormId: null,
                actorId:             $actorId,
            );

            // 1. Aggregates
            $totals       = $this->aggregator->fiscalYearTotals($companyId, $period);
            $totalAssets  = $this->aggregator->totalAssetsAsOf($companyId, $period);

            // Withholding credits — pull the rich summary so we can lock the
            // contributing certs into this filing (preventing double-claim).
            $creditSummary = $this->creditAggregator->execute($companyId, $year);
            $creditableWt  = bccomp($creditableWtOverride, '0', 2) > 0
                ? $creditableWtOverride
                : $creditSummary->totalTaxWithheld;

            // Auto-sum Q1+Q2+Q3 1702Q payments when the user did not provide
            // an explicit override. upToQuarter=4 covers all three prior Qs.
            if (bccomp($taxPaymentsToDate, '0', 2) === 0) {
                $taxPaymentsToDate = $this->aggregator->priorQuarterTaxPayments(
                    $companyId, '1702Q', $year, 4,
                );
            }

            // 2. Taxable income
            $netSales    = bcsub($totals['gross_revenue'], $totals['sales_returns'], 2);
            $grossIncome = bcsub($netSales, $totals['cost_of_sales'], 2);

            // OSD election (RR 2-2010): 40% of gross income replaces itemized
            // operating expenses entirely. Taxable income recomputes from
            // gross_income − OSD + other_income − other_expenses.
            // Without OSD, we trust the JE-derived net_income_before_tax which
            // already accounts for full itemized deductions.
            $deductionMethod = $useOsd ? 'osd' : 'itemized';
            $allowableDeductions = $useOsd
                ? $this->osd->forCorporate(
                    grossSales:   $totals['gross_revenue'],
                    salesReturns: $totals['sales_returns'],
                    costOfSales:  $totals['cost_of_sales'],
                )
                : $totals['operating_expenses'];

            $taxableIncome = $useOsd
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

            // 3. Apply CREATE Act
            $tax = $this->calculator->compute(
                taxableIncome:            $taxableIncome,
                grossIncome:              $grossIncome,
                totalAssetsExcludingLand: $totalAssets,
            );

            // 4. Subtract credits
            $totalCredits = bcadd(bcadd($priorExcessCredits, $creditableWt, 2), $taxPaymentsToDate, 2);
            $taxStillDue  = bcsub($tax['tax_due_higher'], $totalCredits, 2);

            // 5. Build form
            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1702RT',
                period:    $period,
            );
            $form->lines = [];

            // Line numbers map to BIR 1702-RT (RR 21-2020)
            $form->addLine(new BirFormLine('14',  'Sales / Revenues / Receipts (Gross)',     $totals['gross_revenue']));
            $form->addLine(new BirFormLine('15',  'Less: Sales Returns, Discounts, Allowances', $totals['sales_returns']));
            $form->addLine(new BirFormLine('15A', 'Net Sales / Revenues / Receipts',          $netSales));
            $form->addLine(new BirFormLine('16',  'Less: Cost of Sales / Services',           $totals['cost_of_sales']));
            $form->addLine(new BirFormLine('17',  'Gross Income from Operation',              $grossIncome));
            $form->addLine(new BirFormLine(
                '18',
                $useOsd
                    ? 'Less: Optional Standard Deduction (40% of Gross Income, RR 2-2010)'
                    : 'Less: Allowable Itemized Deductions',
                $allowableDeductions,
            ));
            $form->addLine(new BirFormLine('18A', 'Other Income (non-operating)',             $totals['other_income']));
            $form->addLine(new BirFormLine('18B', 'Other Expenses (non-operating)',           $totals['other_expenses']));
            $form->addLine(new BirFormLine('19',  'Taxable Income',                           $taxableIncome));
            $form->addLine(new BirFormLine('20',  'Tax Rate Applied',                          $tax['applied_rate_pct']));
            $form->addLine(new BirFormLine('20A', 'Regular Tax (Income × Rate)',              $tax['regular_tax']));
            $form->addLine(new BirFormLine('20B', 'MCIT (Gross Income × 2%)',                  $tax['mcit_amount']));
            $form->addLine(new BirFormLine('20C', 'Tax Due (higher of Regular vs MCIT)',      $tax['tax_due_higher']));
            $form->addLine(new BirFormLine('21A', 'Less: Prior Year Excess Credits',          $priorExcessCredits));
            $form->addLine(new BirFormLine('22',  'Less: Creditable WT (sum of 2307 received)', $creditableWt));
            $form->addLine(new BirFormLine('22A', 'Less: Quarterly ITR Payments Made',         $taxPaymentsToDate));
            $form->addLine(new BirFormLine('22B', 'Total Tax Credits / Payments',              $totalCredits));
            $form->addLine(new BirFormLine('23',  'Tax Still Due / (Overpayment)',             $taxStillDue));

            $form->markGenerated(taxDue: bccomp($taxStillDue, '0', 2) > 0 ? $taxStillDue : '0.00');
            $form->data = [
                'is_msme'              => $tax['is_msme'],
                'total_assets'         => $totalAssets,
                'totals'               => $totals,
                'tax'                  => $tax,
                'deduction_method'     => $deductionMethod,
                'allowable_deductions' => $allowableDeductions,
            ];

            $this->forms->save($form);

            // Lock the 2307 certs we just credited so they can't be claimed
            // again in a different filing. Only runs when we used our own
            // computed credit, NOT when the user supplied an explicit override
            // (override path implies they're managing the lock themselves).
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
                    'form_type'        => '1702RT',
                    'year'             => $year,
                    'is_msme'          => $tax['is_msme'],
                    'deduction_method' => $deductionMethod,
                    'tax_due'          => $tax['tax_due_higher'],
                    'tax_still_due'    => $taxStillDue,
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1702RT',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $taxStillDue,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
