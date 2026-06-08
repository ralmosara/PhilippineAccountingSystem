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
 * Generates BIR Form 2550M (Monthly VAT Declaration) for a specific month.
 *
 * Flow:
 *   1. Aggregate sales/purchases for the month from Sales + Procurement
 *      (via FormDataAggregatorContract — single read-only cross-schema query)
 *   2. VatReturnBuilder produces line-level data (1A, 1B, 8, 18B, 21, 22, 24)
 *   3. Persist BirForm + BirFormLines
 *   4. Render PDF
 *   5. Audit + dispatch BirFormGenerated event
 *
 * Idempotent: returns the existing form if already generated for the period.
 */
final readonly class GenerateForm2550M
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

    public function execute(
        string $companyId,
        int $year,
        int $month,
        string $actorId,
    ): BirForm {
        $period = FormPeriod::month($year, $month);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            // Idempotency
            $existing = $this->forms->findByPeriod($companyId, '2550M', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            // 1. Aggregate from Sales + Procurement
            $aggregates = $this->aggregator->aggregateVatReturn($companyId, $period);
            $aggregates['prior_excess_input'] = $this->aggregator->priorPeriodExcessInput($companyId, $period);

            // 2. Build form
            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '2550M',
                period:    $period,
            );
            $form->lines = [];                                   // recompute on regenerate
            $this->builder->build($form, $aggregates);

            // 3. Persist
            $this->forms->save($form);

            // 4. Render PDF
            $form->pdfPath = $this->pdf->render($form);
            $this->forms->save($form);

            // 5. Audit + event
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'birform.generated',
                aggregate:   'BirForm',
                aggregateId: $form->id->value,
                payload: [
                    'form_type' => '2550M',
                    'period'    => $period->label(),
                    'tax_due'   => $form->taxDue,
                    'pdf_path'  => $form->pdfPath,
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '2550M',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $form->taxDue,
                generatedBy: $actorId,
            ));

            return $form;
        });
    }
}
