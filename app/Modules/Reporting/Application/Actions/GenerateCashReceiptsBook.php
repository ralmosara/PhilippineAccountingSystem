<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\BooksOfAccountsAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final readonly class GenerateCashReceiptsBook extends GenerateBook
{
    public function __construct(
        ReportRepositoryContract $repository,
        ReportPdfRendererContract $pdf,
        AuditWriterContract $audit,
        private BooksOfAccountsAggregatorContract $aggregator,
    ) {
        parent::__construct($repository, $pdf, $audit);
    }

    protected function reportType(): string
    {
        return 'cash_receipts_book';
    }

    protected function buildPayload(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->aggregator->cashReceiptsRows($companyId, $period);

        $total = '0';
        $byMethod = [];
        foreach ($rows as $r) {
            $total = bcadd($total, $r['amount'], 2);
            $byMethod[$r['payment_method']] = bcadd(
                $byMethod[$r['payment_method']] ?? '0',
                $r['amount'],
                2,
            );
        }

        return [
            'rows'       => $rows,
            'row_count'  => count($rows),
            'total'      => $total,
            'by_method'  => $byMethod,
        ];
    }
}
