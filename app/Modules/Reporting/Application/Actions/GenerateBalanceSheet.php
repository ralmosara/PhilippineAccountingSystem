<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\ReportDataAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\Entities\BalanceSheet;
use App\Modules\Reporting\Domain\Services\BalanceSheetBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class GenerateBalanceSheet
{
    public function __construct(
        private ReportDataAggregatorContract $aggregator,
        private BalanceSheetBuilder $builder,
        private ReportRepositoryContract $repository,
        private ReportPdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{id: string, balance_sheet: BalanceSheet, pdf_path: string}
     */
    public function execute(string $companyId, DateTimeImmutable $asOfDate, string $actorId): array
    {
        $period = ReportPeriod::asOf($asOfDate);

        return DB::transaction(function () use ($companyId, $period, $actorId, $asOfDate) {
            $rows           = $this->aggregator->balancesAsOf($companyId, $period);
            $currentEarnings = $this->aggregator->netIncomeForFiscalYear($companyId, $period);

            $bs = $this->builder->build($companyId, $period, $rows, $currentEarnings);

            $payload = [
                'as_of_date'            => $asOfDate->format('Y-m-d'),
                'sections'              => array_map(
                    fn ($lines) => array_map(fn ($l) => [
                        'account_code'  => $l->accountCode,
                        'account_name'  => $l->accountName,
                        'amount'        => $l->amount,
                    ], $lines),
                    $bs->sections,
                ),
                'current_year_earnings' => $bs->currentYearEarnings,
                'total_assets'          => $bs->totalAssets,
                'total_liabilities'     => $bs->totalLiabilities,
                'total_equity'          => $bs->totalEquity,
                'liabilities_and_equity'=> $bs->totalLiabilitiesAndEquity(),
                'is_balanced'           => $bs->isBalanced(),
            ];

            $pdfPath = $this->pdf->render('balance_sheet', $companyId, $payload);

            $id = Uuid::uuid4()->toString();
            $this->repository->save(
                id:          $id,
                companyId:   $companyId,
                reportType:  'balance_sheet',
                periodFrom:  $period->fiscalYearStart()->format('Y-m-d'),
                periodTo:    $asOfDate->format('Y-m-d'),
                asOfDate:    $asOfDate->format('Y-m-d'),
                payload:     $payload,
                pdfPath:     $pdfPath,
                csvPath:     null,
                generatedBy: $actorId,
            );

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'report.balance_sheet_generated',
                aggregate:   'ReportRun',
                aggregateId: $id,
                payload: [
                    'as_of_date'    => $asOfDate->format('Y-m-d'),
                    'total_assets'  => $bs->totalAssets,
                    'is_balanced'   => $bs->isBalanced(),
                ],
            );

            return ['id' => $id, 'balance_sheet' => $bs, 'pdf_path' => $pdfPath];
        });
    }
}
