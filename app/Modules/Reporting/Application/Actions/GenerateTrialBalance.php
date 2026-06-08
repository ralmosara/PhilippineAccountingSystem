<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\ReportDataAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\Entities\TrialBalance;
use App\Modules\Reporting\Domain\Services\TrialBalanceBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class GenerateTrialBalance
{
    public function __construct(
        private ReportDataAggregatorContract $aggregator,
        private TrialBalanceBuilder $builder,
        private ReportRepositoryContract $repository,
        private ReportPdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{id: string, trial_balance: TrialBalance, pdf_path: string}
     */
    public function execute(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        string $actorId,
    ): array {
        $period = ReportPeriod::forPeriod($from, $to);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $rows = $this->aggregator->trialBalanceData($companyId, $period);
            $tb   = $this->builder->build($companyId, $period, $rows);

            $payload = [
                'period'       => $period->label(),
                'period_from'  => $period->from->format('Y-m-d'),
                'period_to'    => $period->to->format('Y-m-d'),
                'total_debit'  => $tb->totalDebit,
                'total_credit' => $tb->totalCredit,
                'is_balanced'  => $tb->isBalanced(),
                'lines'        => array_map(fn ($l) => [
                    'account_code' => $l->accountCode,
                    'account_name' => $l->accountName,
                    'account_type' => $l->accountType,
                    'debit'        => $l->debit,
                    'credit'       => $l->credit,
                    'balance'      => $l->balance,
                ], $tb->lines),
            ];

            $pdfPath = $this->pdf->render('trial_balance', $companyId, $payload);

            $id = Uuid::uuid4()->toString();
            $this->repository->save(
                id:          $id,
                companyId:   $companyId,
                reportType:  'trial_balance',
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
                eventType:   'report.trial_balance_generated',
                aggregate:   'ReportRun',
                aggregateId: $id,
                payload: [
                    'period'      => $period->label(),
                    'is_balanced' => $tb->isBalanced(),
                    'total'       => $tb->totalDebit,
                ],
            );

            return ['id' => $id, 'trial_balance' => $tb, 'pdf_path' => $pdfPath];
        });
    }
}
