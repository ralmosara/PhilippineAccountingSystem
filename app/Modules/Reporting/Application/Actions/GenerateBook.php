<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Shared persistence + audit + render workflow for the 6 BIR books.
 *
 * Each book action prepares its `report_type` slug + payload via a closure,
 * then this base writes the run, renders the PDF, and emits the audit event.
 *
 * Final classes per book are thin wrappers — they exist as named classes
 * so single-action controllers stay 1:1 with verbs (Taylor Otwell rule).
 */
abstract readonly class GenerateBook
{
    public function __construct(
        protected ReportRepositoryContract $repository,
        protected ReportPdfRendererContract $pdf,
        protected AuditWriterContract $audit,
    ) {
    }

    abstract protected function reportType(): string;

    /** @return array<string, mixed> */
    abstract protected function buildPayload(string $companyId, ReportPeriod $period): array;

    /**
     * @return array{id: string, pdf_path: string, payload: array<string, mixed>}
     */
    public function execute(string $companyId, ReportPeriod $period, string $actorId): array
    {
        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $payload = $this->buildPayload($companyId, $period);
            $payload['period']      = $period->label();
            $payload['period_from'] = $period->from->format('Y-m-d');
            $payload['period_to']   = $period->to->format('Y-m-d');

            $pdfPath = $this->pdf->render($this->reportType(), $companyId, $payload);

            $id = Uuid::uuid4()->toString();
            $this->repository->save(
                id:          $id,
                companyId:   $companyId,
                reportType:  $this->reportType(),
                periodFrom:  $period->from->format('Y-m-d'),
                periodTo:    $period->to->format('Y-m-d'),
                asOfDate:    null,
                payload:     $payload,
                pdfPath:     $pdfPath,
                csvPath:     null,
                generatedBy: $actorId,
            );

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'report.'.$this->reportType().'_generated',
                aggregate:   'ReportRun',
                aggregateId: $id,
                payload: [
                    'period'  => $period->label(),
                    'row_count' => count($payload['rows'] ?? $payload['entries'] ?? $payload['ledgers'] ?? []),
                ],
            );

            return ['id' => $id, 'pdf_path' => $pdfPath, 'payload' => $payload];
        });
    }
}
