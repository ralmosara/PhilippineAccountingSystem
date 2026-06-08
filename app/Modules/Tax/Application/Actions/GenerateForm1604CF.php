<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\FormDataAggregatorContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Domain\Entities\AlphalistEntry;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use App\Modules\Tax\Domain\Events\BirFormGenerated;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 1604-CF — Annual Information Return of Income Taxes
 * Withheld on Compensation.
 *
 * Filing deadline: January 31 of the following year (RR 11-2018).
 *
 * Contains an alphalist of every employee paid during the year, split into:
 *   - Schedule 7.1: Minimum Wage Earners (MWE) — exempt from WT
 *   - Schedule 7.2: Employees Other Than MWE — regular tax-withheld
 *
 * The DAT attachment (for eBIRForms upload) is produced separately via
 * ExportAlphalistDat::execute(bir_form_id) once this action runs.
 */
final readonly class GenerateForm1604CF
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private FormDataAggregatorContract $aggregator,
        private PdfRendererContract $pdf,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $companyId, int $year, string $actorId): BirForm
    {
        $period = FormPeriod::year($year);

        return DB::transaction(function () use ($companyId, $period, $actorId, $year) {
            $existing = $this->forms->findByPeriod($companyId, '1604CF', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            $rows = $this->aggregator->annualEmployeeAlphalist($companyId, $period);

            // Totals + schedule split (MWE vs non-MWE)
            $totalGross = '0';
            $totalTaxable = '0';
            $totalNontaxable = '0';
            $totalWt = '0';
            $mweCount = 0;
            $nonMweCount = 0;

            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1604CF',
                period:    $period,
            );
            $form->lines = [];
            $form->alphalistEntries = [];

            foreach ($rows as $r) {
                $totalGross      = bcadd($totalGross,      $r['gross_compensation'],      2);
                $totalNontaxable = bcadd($totalNontaxable, $r['nontaxable_compensation'], 2);
                $totalTaxable    = bcadd($totalTaxable,    $r['taxable_compensation'],    2);
                $totalWt         = bcadd($totalWt,         $r['withholding_tax'],         2);

                if ($r['is_mwe']) {
                    $mweCount++;
                    $schedule = '7_1';
                } else {
                    $nonMweCount++;
                    $schedule = '7_2';
                }

                $form->addAlphalistEntry(new AlphalistEntry(
                    schedule:        $schedule,
                    tin:             $r['tin'] ?: '000-000-000-000',
                    registeredName:  $r['full_name'],
                    atcCode:         'WC050',                   // BIR ATC for compensation WT
                    incomePayment:   $r['gross_compensation'],
                    taxWithheld:     $r['withholding_tax'],
                    taxType:         'C',                       // Compensation
                ));
            }

            // Form summary lines (1604-CF totals page)
            $form->addLine(new BirFormLine('A',  'Total Compensation Paid During the Year',  $totalGross));
            $form->addLine(new BirFormLine('B',  'Non-Taxable Compensation',                  $totalNontaxable));
            $form->addLine(new BirFormLine('C',  'Taxable Compensation',                      $totalTaxable));
            $form->addLine(new BirFormLine('D',  'Total Tax Withheld',                        $totalWt));
            $form->addLine(new BirFormLine('E',  'MWE Employee Count (Schedule 7.1)',         (string) $mweCount));
            $form->addLine(new BirFormLine('F',  'Non-MWE Employee Count (Schedule 7.2)',     (string) $nonMweCount));

            $form->markGenerated(taxDue: $totalWt);
            $form->data = [
                'totals' => [
                    'gross'        => $totalGross,
                    'taxable'      => $totalTaxable,
                    'nontaxable'   => $totalNontaxable,
                    'wt'           => $totalWt,
                    'mwe_count'    => $mweCount,
                    'non_mwe_count'=> $nonMweCount,
                ],
            ];

            $this->forms->save($form);
            $form->pdfPath = $this->pdf->render($form);
            $this->forms->save($form);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'birform.generated',
                aggregate:   'BirForm',
                aggregateId: $form->id->value,
                payload: [
                    'form_type'      => '1604CF',
                    'year'           => $year,
                    'employee_count' => $mweCount + $nonMweCount,
                    'total_wt'       => $totalWt,
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1604CF',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $totalWt,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
