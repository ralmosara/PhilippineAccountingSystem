<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\ReportDataAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\Entities\CashFlowStatement;
use App\Modules\Reporting\Domain\Services\CashFlowBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class GenerateCashFlowStatement
{
    public function __construct(
        private ReportDataAggregatorContract $aggregator,
        private CashFlowBuilder $builder,
        private ReportRepositoryContract $repository,
        private ReportPdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{id: string, cash_flow: CashFlowStatement, pdf_path: string}
     */
    public function execute(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        string $actorId,
    ): array {
        $period = ReportPeriod::forPeriod($from, $to);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $data = $this->aggregator->cashFlowData($companyId, $period);
            $cf   = $this->builder->build($companyId, $period, $data);

            $payload = [
                'period'         => $period->label(),
                'period_from'    => $period->from->format('Y-m-d'),
                'period_to'      => $period->to->format('Y-m-d'),
                'operating'      => $cf->operatingActivities,
                'investing'      => $cf->investingActivities,
                'financing'      => $cf->financingActivities,
                'total_operating'=> $cf->totalOperating,
                'total_investing'=> $cf->totalInvesting,
                'total_financing'=> $cf->totalFinancing,
                'net_change'     => $cf->netChange(),
                'beginning_cash' => $cf->beginningCash,
                'ending_cash'    => $cf->endingCash,
                'reconciles'     => $cf->reconciles(),
            ];

            $pdfPath = $this->pdf->render('cash_flow_statement', $companyId, $payload);

            $id = Uuid::uuid4()->toString();
            $this->repository->save(
                id:          $id,
                companyId:   $companyId,
                reportType:  'cash_flow_statement',
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
                eventType:   'report.cash_flow_generated',
                aggregate:   'ReportRun',
                aggregateId: $id,
                payload: [
                    'period'       => $period->label(),
                    'net_change'   => $cf->netChange(),
                    'reconciles'   => $cf->reconciles(),
                ],
            );

            return ['id' => $id, 'cash_flow' => $cf, 'pdf_path' => $pdfPath];
        });
    }
}
