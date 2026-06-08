<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Reporting\Application\Contracts\BooksOfAccountsAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final readonly class GenerateGeneralJournal extends GenerateBook
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
        return 'general_journal';
    }

    protected function buildPayload(string $companyId, ReportPeriod $period): array
    {
        $entries = $this->aggregator->generalJournalEntries($companyId, $period);

        $totalDebit  = '0';
        $totalCredit = '0';
        foreach ($entries as $e) {
            foreach ($e['lines'] as $l) {
                $totalDebit  = bcadd($totalDebit,  $l['debit'],  2);
                $totalCredit = bcadd($totalCredit, $l['credit'], 2);
            }
        }

        return [
            'entries'       => $entries,
            'entry_count'   => count($entries),
            'total_debit'   => $totalDebit,
            'total_credit'  => $totalCredit,
            'is_balanced'   => bccomp($totalDebit, $totalCredit, 2) === 0,
        ];
    }
}
