<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\AnnualIncomeTaxAggregatorContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Application\Queries\AggregateWithholdingCreditsForYear;
use App\Modules\Tax\Application\Actions\RecordOrConfirmOsdElection;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use App\Modules\Tax\Domain\Events\BirFormGenerated;
use App\Modules\Tax\Domain\Services\IndividualIncomeTaxCalculator;
use App\Modules\Tax\Domain\Services\OsdCalculator;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 1701Q — Quarterly Income Tax Return for Self-Employed
 * Individuals, Estates and Trusts (RR 8-2018).
 *
 * Filing deadlines (cumulative YTD basis):
 *   Q1 — May 15  (covers Jan–Mar)
 *   Q2 — Aug 15  (covers Jan–Jun)
 *   Q3 — Nov 15  (covers Jan–Sep)
 *   Q4 — folded into annual 1701, due April 15 of next year
 *
 * Computation:
 *   1. Sum cumulative YTD revenue / COGS / OpEx through end of quarter
 *   2. Apply same deduction regime chosen at Q1 (itemized | OSD | 8% flat)
 *   3. Compute cumulative tax due
 *   4. Subtract prior-quarter tax payments + creditable WT received YTD
 *   5. Result = tax due THIS quarter (≥ 0; refunds claimed on annual)
 *
 * Election lock-in (RR 8-2018 § 4): the deduction regime declared on Q1 is
 * irrevocable for the entire year. This action enforces that by reading
 * Q1's `deduction_method` from `data` when generating Q2 / Q3.
 */
final readonly class GenerateForm1701Q
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

    public function execute(
        string $companyId,
        int $year,
        int $quarter,                    // 1..3 (Q4 rolls into annual)
        string $actorId,
        bool   $electFlat8Percent       = false,
        bool   $useOsd                  = false,
        string $personalExemption       = '0.00',
        string $creditableWtOverride    = '0.00',
    ): BirForm {
        if ($quarter < 1 || $quarter > 3) {
            throw new DomainException(
                "1701Q: invalid quarter {$quarter}. Q4 is filed via the annual 1701, not a quarterly Q-return."
            );
        }

        $this->osd->assertNotCombinedWithFlat8Percent($useOsd, $electFlat8Percent);

        return DB::transaction(function () use (
            $companyId, $year, $quarter, $actorId,
            $electFlat8Percent, $useOsd, $personalExemption, $creditableWtOverride
        ) {
            $period = FormPeriod::cumulativeThroughQuarter($year, $quarter);

            $existing = $this->forms->findByPeriod($companyId, '1701Q', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            // 1. Enforce / record the year's deduction-regime election
            //    (RR 2-2010 § 7, RR 8-2018 § 4). The election is stored in
            //    tax.osd_elections — authoritative across quarterlies + annual.
            $thisRegime = $electFlat8Percent ? 'flat_8pct' : ($useOsd ? 'osd' : 'itemized');
            $this->osdElection->execute(
                companyId:           $companyId,
                fiscalYear:          $year,
                taxpayerType:        'individual',
                regime:              $thisRegime,
                declaredInFormType:  '1701Q',
                declaredInQuarter:   $quarter,
                declaredInBirFormId: null,           // form not yet persisted — election is the canonical record
                actorId:             $actorId,
            );

            // 2. Cumulative YTD aggregates
            $totals = $this->aggregator->fiscalYearTotals($companyId, $period);

            $creditSummary = $this->creditAggregator->execute($companyId, $year);
            $creditableWt  = bccomp($creditableWtOverride, '0', 2) > 0
                ? $creditableWtOverride
                : $creditSummary->totalTaxWithheld;

            $netSales    = bcsub($totals['gross_revenue'], $totals['sales_returns'], 2);
            $grossIncome = bcsub($netSales, $totals['cost_of_sales'], 2);

            // 3. Deduction regime
            $deductionMethod     = $useOsd ? 'osd' : 'itemized';
            $allowableDeductions = $useOsd
                ? $this->osd->forIndividual($totals['gross_revenue'])
                : $totals['operating_expenses'];

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

            // 4. Tax due on cumulative income (annualized for the 8% flat rule)
            $tax = $this->calculator->compute(
                taxableIncome:     $taxableIncome,
                grossSales:        $totals['gross_revenue'],
                electFlat8Percent: $electFlat8Percent,
            );

            // 5. Subtract prior-quarter payments + creditable WT
            $priorPayments = $this->aggregator->priorQuarterTaxPayments(
                $companyId, '1701Q', $year, $quarter,
            );
            $totalCredits = bcadd($priorPayments, $creditableWt, 2);
            $taxStillDue  = bcsub($tax['tax_due'], $totalCredits, 2);
            // Refunds are claimed on the annual 1701; quarterlies clamp at 0
            // (BIR doesn't refund per-quarter overpayments).
            if (bccomp($taxStillDue, '0', 2) < 0) {
                $taxStillDue = '0.00';
            }

            // 6. Build form
            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1701Q',
                period:    $period,
            );
            $form->lines = [];

            // Line codes mirror BIR Form 1701Q v.2018 (TRAIN-revised)
            $form->addLine(new BirFormLine('22',  'Gross Sales / Receipts / Revenues (YTD)',     $totals['gross_revenue']));
            $form->addLine(new BirFormLine('23',  'Less: Sales Returns, Discounts',              $totals['sales_returns']));
            $form->addLine(new BirFormLine('24',  'Net Sales / Receipts (YTD)',                  $netSales));
            $form->addLine(new BirFormLine('25',  'Less: Cost of Sales / Services (YTD)',        $totals['cost_of_sales']));
            $form->addLine(new BirFormLine('26',  'Gross Income (YTD)',                          $grossIncome));
            $form->addLine(new BirFormLine(
                '27',
                $useOsd
                    ? 'Less: Optional Standard Deduction (40% of Gross Sales, RR 2-2010)'
                    : 'Less: Allowable Deductions (Itemized, YTD)',
                $allowableDeductions,
            ));
            $form->addLine(new BirFormLine('28A', 'Other Income (YTD, non-operating)',           $totals['other_income']));
            $form->addLine(new BirFormLine('28B', 'Other Expenses (YTD, non-operating)',         $totals['other_expenses']));
            $form->addLine(new BirFormLine('29',  'Net Income from Operation (YTD)',             $netIncomeBeforeTax));
            $form->addLine(new BirFormLine('30',  'Personal/Additional Exemptions (legacy)',     $personalExemption));
            $form->addLine(new BirFormLine('31',  'Taxable Income (YTD)',                        $taxableIncome));
            $form->addLine(new BirFormLine('32',  'Computation Method',                          $tax['method'] === 'flat_8pct' ? '8% Flat' : 'Graduated'));
            $form->addLine(new BirFormLine('33',  'Cumulative Tax Due',                          $tax['tax_due']));
            $form->addLine(new BirFormLine('34A', 'Less: Tax Paid in Prior Quarters',            $priorPayments));
            $form->addLine(new BirFormLine('34B', 'Less: Creditable WT (sum of 2307 received)',  $creditableWt));
            $form->addLine(new BirFormLine('34C', 'Total Tax Credits / Payments',                $totalCredits));
            $form->addLine(new BirFormLine('35',  'Tax Still Due This Quarter',                  $taxStillDue));

            $form->markGenerated(taxDue: $taxStillDue);
            $form->data = [
                'method'               => $tax['method'],
                'rate_pct'             => $tax['applied_rate_pct'],
                'deduction_method'     => $deductionMethod,
                'allowable_deductions' => $allowableDeductions,
                'totals'               => $totals,
                'cumulative_tax_due'   => $tax['tax_due'],
                'prior_q_payments'     => $priorPayments,
                'creditable_wt'        => $creditableWt,
                'tax_still_due'        => $taxStillDue,
            ];

            $this->forms->save($form);

            // Lock contributing 2307s when we used our own computed credit
            if (bccomp($creditableWtOverride, '0', 2) === 0
                && $creditSummary->claimableCertIds !== []) {
                $this->form2307Repo->markClaimed(
                    array_map(
                        fn (string $id) => new \App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId($id),
                        $creditSummary->claimableCertIds,
                    ),
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
                    'form_type'        => '1701Q',
                    'year'             => $year,
                    'quarter'          => $quarter,
                    'method'           => $tax['method'],
                    'deduction_method' => $deductionMethod,
                    'cumulative_tax'   => $tax['tax_due'],
                    'tax_still_due'    => $taxStillDue,
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1701Q',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $taxStillDue,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
