<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\BooksOfAccountsAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Generates the General Ledger. Unlike the other books, this one accepts
 * an optional `$accountId` filter — a focused per-account ledger or a
 * full GL for every postable account.
 *
 * Standalone (not extending GenerateBook) because the optional account
 * filter doesn't fit the shared base's signature cleanly.
 */
final readonly class GenerateGeneralLedger
{
    public function __construct(
        private BooksOfAccountsAggregatorContract $aggregator,
        private ReportRepositoryContract $repository,
        private ReportPdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{id: string, pdf_path: string, payload: array<string, mixed>}
     */
    public function execute(
        string $companyId,
        ReportPeriod $period,
        string $actorId,
        ?string $accountId = null,
    ): array {
        return DB::transaction(function () use ($companyId, $period, $actorId, $accountId) {
            $ledgers = $this->aggregator->generalLedgerByAccount($companyId, $period, $accountId);

            $payload = [
                'period'      => $period->label(),
                'period_from' => $period->from->format('Y-m-d'),
                'period_to'   => $period->to->format('Y-m-d'),
                'account_id'  => $accountId,
                'ledgers'     => $ledgers,
                'ledger_count'=> count($ledgers),
            ];

            $pdfPath = $this->pdf->render('general_ledger', $companyId, $payload);

            $id = Uuid::uuid4()->toString();
            $this->repository->save(
                id:          $id,
                companyId:   $companyId,
                reportType:  'general_ledger',
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
                eventType:   'report.general_ledger_generated',
                aggregate:   'ReportRun',
                aggregateId: $id,
                payload: [
                    'period'      => $period->label(),
                    'account_id'  => $accountId,
                    'ledger_count'=> count($ledgers),
                ],
            );

            return ['id' => $id, 'pdf_path' => $pdfPath, 'payload' => $payload];
        });
    }
}
