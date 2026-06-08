<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\BooksOfAccountsAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final readonly class GenerateSalesBook extends GenerateBook
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
        return 'sales_book';
    }

    protected function buildPayload(string $companyId, ReportPeriod $period): array
    {
        $rows = $this->aggregator->salesBookRows($companyId, $period);

        $totals = ['vatable' => '0', 'zero' => '0', 'exempt' => '0',
                   'vat' => '0', 'senior_pwd' => '0', 'withheld_vat' => '0', 'total' => '0'];

        foreach ($rows as $r) {
            $totals['vatable']      = bcadd($totals['vatable'],      $r['vatable_sales'],        2);
            $totals['zero']         = bcadd($totals['zero'],         $r['vat_zero_rated_sales'], 2);
            $totals['exempt']       = bcadd($totals['exempt'],       $r['vat_exempt_sales'],     2);
            $totals['vat']          = bcadd($totals['vat'],          $r['vat_amount'],           2);
            $totals['senior_pwd']   = bcadd($totals['senior_pwd'],   $r['senior_pwd_discount'],  2);
            $totals['withheld_vat'] = bcadd($totals['withheld_vat'], $r['withheld_vat'],         2);
            $totals['total']        = bcadd($totals['total'],        $r['total'],                2);
        }

        return ['rows' => $rows, 'row_count' => count($rows), 'totals' => $totals];
    }
}
