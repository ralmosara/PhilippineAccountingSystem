<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\ReportDataAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\Entities\IncomeStatement;
use App\Modules\Reporting\Domain\Services\IncomeStatementBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class GenerateIncomeStatement
{
    public function __construct(
        private ReportDataAggregatorContract $aggregator,
        private IncomeStatementBuilder $builder,
        private ReportRepositoryContract $repository,
        private ReportPdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{id: string, income_statement: IncomeStatement, pdf_path: string}
     */
    public function execute(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        string $actorId,
    ): array {
        $period = ReportPeriod::forPeriod($from, $to);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $rows = $this->aggregator->periodBalances($companyId, $period);
            $is   = $this->builder->build($companyId, $period, $rows);

            $payload = [
                'period'           => $period->label(),
                'period_from'      => $period->from->format('Y-m-d'),
                'period_to'        => $period->to->format('Y-m-d'),
                'revenue'          => $this->serializeLines($is->revenue),
                'cost_of_sales'    => $this->serializeLines($is->costOfSales),
                'operating_expenses' => $this->serializeLines($is->operatingExpenses),
                'other_income'     => $this->serializeLines($is->otherIncome),
                'other_expenses'   => $this->serializeLines($is->otherExpenses),
                'income_tax'       => $this->serializeLines($is->incomeTax),
                'totals'           => [
                    'total_revenue'         => $is->totalRevenue,
                    'total_cost_of_sales'   => $is->totalCostOfSales,
                    'gross_profit'          => $is->grossProfit(),
                    'total_operating_expenses' => $is->totalOperatingExpenses,
                    'operating_income'      => $is->operatingIncome(),
                    'total_other_income'    => $is->totalOtherIncome,
                    'total_other_expenses'  => $is->totalOtherExpenses,
                    'income_before_tax'     => $is->incomeBeforeTax(),
                    'total_income_tax'      => $is->totalIncomeTax,
                    'net_income'            => $is->netIncome(),
                ],
            ];

            $pdfPath = $this->pdf->render('income_statement', $companyId, $payload);

            $id = Uuid::uuid4()->toString();
            $this->repository->save(
                id:          $id,
                companyId:   $companyId,
                reportType:  'income_statement',
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
                eventType:   'report.income_statement_generated',
                aggregate:   'ReportRun',
                aggregateId: $id,
                payload: [
                    'period'     => $period->label(),
                    'net_income' => $is->netIncome(),
                ],
            );

            return ['id' => $id, 'income_statement' => $is, 'pdf_path' => $pdfPath];
        });
    }

    /**
     * @param  list<\App\Modules\Reporting\Domain\Entities\FinancialStatementLine>  $lines
     * @return list<array<string, string>>
     */
    private function serializeLines(array $lines): array
    {
        return array_map(fn ($l) => [
            'account_code'        => $l->accountCode,
            'account_name'        => $l->accountName,
            'amount'              => $l->amount,
            'pfrs_classification' => $l->pfrsClassification ?? '',
        ], $lines);
    }
}
