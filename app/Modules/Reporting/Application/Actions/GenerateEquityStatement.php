<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\ReportDataAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\Entities\EquityStatement;
use App\Modules\Reporting\Domain\Services\EquityStatementBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class GenerateEquityStatement
{
    public function __construct(
        private ReportDataAggregatorContract $aggregator,
        private EquityStatementBuilder $builder,
        private ReportRepositoryContract $repository,
        private ReportPdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{id: string, equity_statement: EquityStatement, pdf_path: string}
     */
    public function execute(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        string $actorId,
    ): array {
        $period = ReportPeriod::forPeriod($from, $to);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $data = $this->aggregator->equityMovements($companyId, $period);
            $es   = $this->builder->build($companyId, $period, $data);

            $payload = [
                'period'                 => $period->label(),
                'period_from'            => $period->from->format('Y-m-d'),
                'period_to'              => $period->to->format('Y-m-d'),
                'accounts'               => $es->accounts,
                'total_beginning'        => $es->totalBeginning,
                'total_movement'         => $es->totalMovement,
                'total_ending'           => $es->totalEnding,
                'net_income_for_period'  => $es->netIncomeForPeriod,
                'total_equity'           => $es->totalEquity(),
            ];

            $pdfPath = $this->pdf->render('equity_statement', $companyId, $payload);

            $id = Uuid::uuid4()->toString();
            $this->repository->save(
                id:          $id,
                companyId:   $companyId,
                reportType:  'equity_statement',
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
                eventType:   'report.equity_statement_generated',
                aggregate:   'ReportRun',
                aggregateId: $id,
                payload: [
                    'period'                 => $period->label(),
                    'total_equity'           => $es->totalEquity(),
                    'net_income_for_period'  => $es->netIncomeForPeriod,
                ],
            );

            return ['id' => $id, 'equity_statement' => $es, 'pdf_path' => $pdfPath];
        });
    }
}
