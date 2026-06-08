<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Pdf;

use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DomPdfReportRenderer implements ReportPdfRendererContract
{
    public function render(string $reportType, string $companyId, array $payload): string
    {
        $view = match ($reportType) {
            'trial_balance'           => 'reporting::pdf.trial-balance',
            'balance_sheet'           => 'reporting::pdf.balance-sheet',
            'income_statement'        => 'reporting::pdf.income-statement',
            'cash_flow_statement'     => 'reporting::pdf.cash-flow',
            'equity_statement'        => 'reporting::pdf.equity-statement',
            'general_journal'         => 'reporting::pdf.general-journal',
            'general_ledger'          => 'reporting::pdf.general-ledger',
            'sales_book'              => 'reporting::pdf.sales-book',
            'purchases_book'          => 'reporting::pdf.purchases-book',
            'cash_receipts_book'      => 'reporting::pdf.cash-receipts-book',
            'cash_disbursements_book' => 'reporting::pdf.cash-disbursements-book',
            default                   => 'reporting::pdf.generic',
        };

        // Books of accounts use landscape orientation due to wide column layouts
        $orientation = in_array($reportType, [
            'general_journal', 'general_ledger', 'sales_book', 'purchases_book',
        ], true) ? 'landscape' : 'portrait';

        $company = DB::selectOne(
            'SELECT registered_name, trade_name, tin, address FROM identity.companies WHERE id = ?::uuid',
            [$companyId],
        );

        $pdf = Pdf::loadView($view, [
            'company' => $company,
            'payload' => $payload,
            'period'  => $payload['period'] ?? ($payload['as_of_date'] ?? null),
        ])->setPaper('letter', $orientation);

        $path = sprintf(
            'reporting/%s/%s/%s/%s.pdf',
            $companyId,
            date('Y'),
            $reportType,
            $reportType.'_'.now()->format('Ymd_His'),
        );

        Storage::put($path, $pdf->output());

        return $path;
    }
}
