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
 * Generates BIR Form 1604-E — Annual Information Return of Creditable
 * Income Taxes Withheld (Expanded).
 *
 * Filing deadline: March 1 of the following year (RR 11-2018).
 *
 * Contains an alphalist of every payee (vendor) we issued a 2307 to during
 * the year, grouped by vendor × ATC. Each unique vendor-ATC pair becomes
 * one alphalist entry under Schedule 1.
 */
final readonly class GenerateForm1604E
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
            $existing = $this->forms->findByPeriod($companyId, '1604E', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            $rows = $this->aggregator->annualPayeeAlphalist($companyId, $period);

            $totalIncome = '0';
            $totalWithheld = '0';
            $byAtc = [];

            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1604E',
                period:    $period,
            );
            $form->lines = [];
            $form->alphalistEntries = [];

            foreach ($rows as $r) {
                $totalIncome   = bcadd($totalIncome,   $r['total_income_payment'], 2);
                $totalWithheld = bcadd($totalWithheld, $r['total_tax_withheld'],   2);

                $byAtc[$r['atc_code']] ??= ['income' => '0', 'withheld' => '0', 'payees' => 0];
                $byAtc[$r['atc_code']]['income']   = bcadd($byAtc[$r['atc_code']]['income'],   $r['total_income_payment'], 2);
                $byAtc[$r['atc_code']]['withheld'] = bcadd($byAtc[$r['atc_code']]['withheld'], $r['total_tax_withheld'],   2);
                $byAtc[$r['atc_code']]['payees']++;

                $form->addAlphalistEntry(new AlphalistEntry(
                    schedule:        '1',                                  // 1604-E Schedule 1: EWT payees
                    tin:             $r['tin'] ?: '000-000-000-000',
                    registeredName:  $r['registered_name'],
                    atcCode:         $r['atc_code'],
                    incomePayment:   $r['total_income_payment'],
                    taxWithheld:     $r['total_tax_withheld'],
                    taxType:         'I',                                  // Income (expanded WT)
                ));
            }

            $form->addLine(new BirFormLine('A',  'Total Income Payments Subject to EWT',   $totalIncome));
            $form->addLine(new BirFormLine('B',  'Total Creditable Tax Withheld',           $totalWithheld));
            $form->addLine(new BirFormLine('C',  'Number of Payees',                        (string) count($rows)));
            $form->addLine(new BirFormLine('D',  'Number of Distinct ATCs',                 (string) count($byAtc)));

            $i = 1;
            foreach ($byAtc as $atc => $totals) {
                $form->addLine(new BirFormLine(
                    "B{$i}",
                    "ATC {$atc} — {$totals['payees']} payee(s)",
                    $totals['withheld'],
                    ['atc' => $atc, 'income' => $totals['income'], 'payees' => $totals['payees']],
                ));
                $i++;
            }

            $form->markGenerated(taxDue: $totalWithheld);
            $form->data = [
                'totals'  => ['income' => $totalIncome, 'withheld' => $totalWithheld, 'payee_count' => count($rows)],
                'by_atc'  => $byAtc,
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
                    'form_type'    => '1604E',
                    'year'         => $year,
                    'payee_count'  => count($rows),
                    'total_wt'     => $totalWithheld,
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1604E',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $totalWithheld,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
