<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Pdf;

use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Domain\Entities\BirForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * BIR form PDF renderer using barryvdh/laravel-dompdf.
 *
 * Phase 1: a clean tabular layout that BIR auditors accept for printout.
 * Phase 2: pixel-perfect overlay on the official BIR PDF templates (the
 * eBIRForms package's master PDFs) — that needs a switch to Browsershot
 * (Puppeteer) so the templates render with proper field alignment.
 *
 * The Blade templates live under `resources/views/tax/pdf/` and are
 * registered by ModuleServiceProvider's loadViewsFrom().
 */
final class DomPdfRenderer implements PdfRendererContract
{
    public function render(BirForm $form): string
    {
        $view = match ($form->formType) {
            '2550M', '2550Q' => 'tax::pdf.form-2550',
            '1601EQ'         => 'tax::pdf.form-1601eq',
            '1601C'          => 'tax::pdf.form-1601c',
            '1702RT'         => 'tax::pdf.form-1702',
            '1702Q'          => 'tax::pdf.form-1702q',
            '1701'           => 'tax::pdf.form-1701',
            '1701Q'          => 'tax::pdf.form-1701q',
            '1604CF'         => 'tax::pdf.form-1604cf',
            '1604E'          => 'tax::pdf.form-1604e',
            default          => 'tax::pdf.form-generic',
        };

        $pdf = Pdf::loadView($view, [
            'form'    => $form,
            'lines'   => $form->lines,
            'company' => $this->companyHeader($form->companyId),
        ])->setPaper('letter');

        $path = sprintf(
            'bir/%s/%d/%s/%s_%s.pdf',
            $form->companyId,
            $form->period->year,
            strtolower($form->formType),
            $form->formType,
            $form->period->label(),
        );

        Storage::put($path, $pdf->output());

        return $path;
    }

    public function renderForm2307(string $form2307Id): string
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT f.*, v.registered_name AS vendor_name, v.tin AS vendor_tin,
                   c.registered_name AS company_name, c.tin AS company_tin
            FROM tax.form_2307 f
            INNER JOIN procurement.vendors v   ON v.id = f.vendor_id
            INNER JOIN identity.companies c    ON c.id = f.company_id
            WHERE f.id = ?::uuid
        SQL, [$form2307Id]);

        $pdf = Pdf::loadView('tax::pdf.form-2307', ['cert' => $row])->setPaper('letter');

        $path = sprintf(
            'bir/%s/%s/2307/2307_%s_%s.pdf',
            $row->company_id,
            substr($row->period_from, 0, 4),
            substr($form2307Id, 0, 8),
            substr($row->period_from, 0, 7),
        );

        Storage::put($path, $pdf->output());

        return $path;
    }

    /** @return array<string, mixed> */
    private function companyHeader(string $companyId): array
    {
        $row = DB::selectOne(
            'SELECT registered_name, trade_name, tin, rdo_code, address FROM identity.companies WHERE id = ?::uuid',
            [$companyId],
        );

        return $row ? [
            'name'       => $row->registered_name,
            'trade_name' => $row->trade_name,
            'tin'        => $row->tin,
            'rdo_code'   => $row->rdo_code,
            'address'    => $row->address,
        ] : [];
    }
}
