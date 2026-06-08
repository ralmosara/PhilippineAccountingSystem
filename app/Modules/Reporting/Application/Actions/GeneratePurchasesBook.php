<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\BooksOfAccountsAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final readonly class GeneratePurchasesBook extends GenerateBook
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
        return 'purchases_book';
    }

    protected function buildPayload(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->aggregator->purchasesBookRows($companyId, $period);

        $totals = ['subtotal' => '0', 'vat_input' => '0', 'withholding' => '0', 'total' => '0'];
        foreach ($rows as $r) {
            $totals['subtotal']    = bcadd($totals['subtotal'],    $r['subtotal'],           2);
            $totals['vat_input']   = bcadd($totals['vat_input'],   $r['vat_input'],          2);
            $totals['withholding'] = bcadd($totals['withholding'], $r['withholding_amount'], 2);
            $totals['total']       = bcadd($totals['total'],       $r['total'],              2);
        }

        return ['rows' => $rows, 'row_count' => count($rows), 'totals' => $totals];
    }
}
