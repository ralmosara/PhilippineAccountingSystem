<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\FormDataAggregatorContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Events\BirFormGenerated;
use App\Modules\Tax\Domain\Services\WithholdingReturnBuilder;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 1601-EQ (Quarterly Remittance of Creditable EWT).
 *
 * Pulls all `tax.form_2307` rows for the period, groups by ATC code,
 * builds the return + the SAWT alphalist attachment.
 */
final readonly class GenerateForm1601EQ
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private FormDataAggregatorContract $aggregator,
        private WithholdingReturnBuilder $builder,
        private PdfRendererContract $pdf,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $companyId, int $year, int $quarter, string $actorId): BirForm
    {
        $period = FormPeriod::quarter($year, $quarter);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $existing = $this->forms->findByPeriod($companyId, '1601EQ', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            $form2307Rows = $this->aggregator->listForm2307ForPeriod($companyId, $period);

            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1601EQ',
                period:    $period,
            );
            $form->lines = [];
            $form->alphalistEntries = [];
            $this->builder->build($form, $form2307Rows);

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
                    'form_type'         => '1601EQ',
                    'period'            => $period->label(),
                    'tax_due'           => $form->taxDue,
                    'sawt_entries'      => count($form->alphalistEntries),
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1601EQ',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $form->taxDue,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
