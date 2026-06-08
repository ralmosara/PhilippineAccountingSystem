<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Actions\RecordOrConfirmOsdElection;
use App\Modules\Tax\Application\Contracts\AnnualIncomeTaxAggregatorContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Application\Queries\AggregateWithholdingCreditsForYear;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use App\Modules\Tax\Domain\Events\BirFormGenerated;
use App\Modules\Tax\Domain\Services\CorporateIncomeTaxCalculator;
use App\Modules\Tax\Domain\Services\OsdCalculator;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 1702Q — Quarterly Income Tax Return for Corporations
 * subject to the regular CREATE-Act rates (RR 5-2021).
 *
 * Filing deadlines (cumulative YTD basis, 60 days after quarter-end):
 *   Q1 — May 30   (covers Jan–Mar)
 *   Q2 — Aug 29   (covers Jan–Jun)
 *   Q3 — Nov 29   (covers Jan–Sep)
 *   Q4 — folded into annual 1702-RT, due April 15
 *
 * Notes vs. 1701Q:
 *   - Uses corporate CREATE Act rate (20% MSME / 25% regular)
 *   - MCIT (2% of gross income) applies but ONLY after the corporation's
 *     4th year of operation; at the quarterly level it's computed for
 *     transparency but BIR auditors look at the annual for MCIT.
 *   - OSD basis = 40% of GROSS INCOME (corp rule), not gross sales.
 */
final readonly class GenerateForm1702Q
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

    public function execute(
        string $companyId,
        int $year,
        int $quarter,                  // 1..3 (Q4 rolls into annual 1702-RT)
        string $actorId,
        bool   $useOsd                = false,
        string $creditableWtOverride  = '0.00',
    ): BirForm {
        if ($quarter < 1 || $quarter > 3) {
            throw new DomainException(
                "1702Q: invalid quarter {$quarter}. Q4 is filed via the annual 1702-RT, not a Q-return."
            );
        }

        return DB::transaction(function () use (
            $companyId, $year, $quarter, $actorId, $useOsd, $creditableWtOverride
        ) {
            $period = FormPeriod::cumulativeThroughQuarter($year, $quarter);

            $existing = $this->forms->findByPeriod($companyId, '1702Q', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            // 1. Record/confirm year's deduction regime via the authoritative
            //    tax.osd_elections table (RR 2-2010 § 7).
            $thisRegime = $useOsd ? 'osd' : 'itemized';
            $this->osdElection->execute(
                companyId:           $companyId,
                fiscalYear:          $year,
                taxpayerType:        'corporate',
                regime:              $thisRegime,
                declaredInFormType:  '1702Q',
                declaredInQuarter:   $quarter,
                declaredInBirFormId: null,
                actorId:             $actorId,
            );

            // 2. Cumulative YTD aggregates + total assets at quarter-end
            $totals      = $this->aggregator->fiscalYearTotals($companyId, $period);
            $totalAssets = $this->aggregator->totalAssetsAsOf($companyId, $period);

            $creditSummary = $this->creditAggregator->execute($companyId, $year);
            $creditableWt  = bccomp($creditableWtOverride, '0', 2) > 0
                ? $creditableWtOverride
                : $creditSummary->totalTaxWithheld;

            $netSales    = bcsub($totals['gross_revenue'], $totals['sales_returns'], 2);
            $grossIncome = bcsub($netSales, $totals['cost_of_sales'], 2);

            // 3. Deduction regime (corporate OSD basis = gross income, NOT gross sales)
            $deductionMethod     = $useOsd ? 'osd' : 'itemized';
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

            // 4. CREATE Act rate + MCIT comparison
            $tax = $this->calculator->compute(
                taxableIncome:            $taxableIncome,
                grossIncome:              $grossIncome,
                totalAssetsExcludingLand: $totalAssets,
            );

            // 5. Subtract prior-quarter payments + creditable WT
            $priorPayments = $this->aggregator->priorQuarterTaxPayments(
                $companyId, '1702Q', $year, $quarter,
            );
            $totalCredits = bcadd($priorPayments, $creditableWt, 2);
            $taxStillDue  = bcsub($tax['tax_due_higher'], $totalCredits, 2);
            if (bccomp($taxStillDue, '0', 2) < 0) {
                $taxStillDue = '0.00';            // refunds go on the annual
            }

            // 6. Build form
            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1702Q',
                period:    $period,
            );
            $form->lines = [];

            // Line codes mirror BIR Form 1702Q v.2018 (CREATE-revised)
            $form->addLine(new BirFormLine('15',  'Sales / Revenues / Receipts (YTD, Gross)',    $totals['gross_revenue']));
            $form->addLine(new BirFormLine('16',  'Less: Sales Returns, Discounts',              $totals['sales_returns']));
            $form->addLine(new BirFormLine('16A', 'Net Sales / Revenues (YTD)',                  $netSales));
            $form->addLine(new BirFormLine('17',  'Less: Cost of Sales / Services (YTD)',        $totals['cost_of_sales']));
            $form->addLine(new BirFormLine('18',  'Gross Income from Operation (YTD)',           $grossIncome));
            $form->addLine(new BirFormLine(
                '19',
                $useOsd
                    ? 'Less: Optional Standard Deduction (40% of Gross Income, RR 2-2010)'
                    : 'Less: Allowable Itemized Deductions (YTD)',
                $allowableDeductions,
            ));
            $form->addLine(new BirFormLine('19A', 'Other Income (YTD, non-operating)',           $totals['other_income']));
            $form->addLine(new BirFormLine('19B', 'Other Expenses (YTD, non-operating)',         $totals['other_expenses']));
            $form->addLine(new BirFormLine('20',  'Taxable Income (YTD)',                        $taxableIncome));
            $form->addLine(new BirFormLine('21',  'Tax Rate Applied',                            $tax['applied_rate_pct']));
            $form->addLine(new BirFormLine('21A', 'Regular Tax (Income × Rate)',                 $tax['regular_tax']));
            $form->addLine(new BirFormLine('21B', 'MCIT (Gross Income × 2%)',                    $tax['mcit_amount']));
            $form->addLine(new BirFormLine('21C', 'Cumulative Tax Due (higher of Regular vs MCIT)', $tax['tax_due_higher']));
            $form->addLine(new BirFormLine('22A', 'Less: Tax Paid in Prior Quarters',            $priorPayments));
            $form->addLine(new BirFormLine('22B', 'Less: Creditable WT (sum of 2307 received)',  $creditableWt));
            $form->addLine(new BirFormLine('22C', 'Total Tax Credits / Payments',                $totalCredits));
            $form->addLine(new BirFormLine('23',  'Tax Still Due This Quarter',                  $taxStillDue));

            $form->markGenerated(taxDue: $taxStillDue);
            $form->data = [
                'is_msme'              => $tax['is_msme'],
                'total_assets'         => $totalAssets,
                'totals'               => $totals,
                'tax'                  => $tax,
                'deduction_method'     => $deductionMethod,
                'allowable_deductions' => $allowableDeductions,
                'cumulative_tax_due'   => $tax['tax_due_higher'],
                'prior_q_payments'     => $priorPayments,
                'creditable_wt'        => $creditableWt,
                'tax_still_due'        => $taxStillDue,
            ];

            $this->forms->save($form);

            if (bccomp($creditableWtOverride, '0', 2) === 0
                && $creditSummary->claimableCertIds !== []) {
                $this->form2307Repo->markClaimed(
                    array_map(
                        fn (string $id) => new Form2307ReceivedId($id),
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
                    'form_type'        => '1702Q',
                    'year'             => $year,
                    'quarter'          => $quarter,
                    'is_msme'          => $tax['is_msme'],
                    'deduction_method' => $deductionMethod,
                    'cumulative_tax'   => $tax['tax_due_higher'],
                    'tax_still_due'    => $taxStillDue,
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1702Q',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $taxStillDue,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
