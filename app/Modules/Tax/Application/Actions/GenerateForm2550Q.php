<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\FormDataAggregatorContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Events\BirFormGenerated;
use App\Modules\Tax\Domain\Services\VatReturnBuilder;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 2550Q (Quarterly VAT Return).
 *
 * Same builder as 2550M, but the period covers Q1-Q4 and the form type
 * differs. Per BIR rules, a 2550Q replaces the 3 underlying 2550M filings
 * for that quarter — they are reconciled at quarter-close.
 */
final readonly class GenerateForm2550Q
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private FormDataAggregatorContract $aggregator,
        private VatReturnBuilder $builder,
        private PdfRendererContract $pdf,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $companyId, int $year, int $quarter, string $actorId): BirForm
    {
        $period = FormPeriod::quarter($year, $quarter);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $existing = $this->forms->findByPeriod($companyId, '2550Q', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            $aggregates = $this->aggregator->aggregateVatReturn($companyId, $period);
            $aggregates['prior_excess_input'] = $this->aggregator->priorPeriodExcessInput($companyId, $period);

            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '2550Q',
                period:    $period,
            );
            $form->lines = [];
            $this->builder->build($form, $aggregates);
            $this->forms->save($form);

            $form->pdfPath = $this->pdf->render($form);
            $this->forms->save($form);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'birform.generated',
                aggregate:   'BirForm',
                aggregateId: $form->id->value,
                payload: ['form_type' => '2550Q', 'period' => $period->label(), 'tax_due' => $form->taxDue],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '2550Q',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $form->taxDue,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
