<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Renders a Form 2307 PDF for an already-issued certificate row in
 * tax.form_2307. Idempotent — overwrites the PDF on regeneration.
 *
 * The 2307 row itself is created by Procurement\Application\Actions\IssueForm2307
 * when a vendor bill posts; this Action only handles the rendering.
 */
final readonly class GenerateForm2307Pdf
{
    public function __construct(
        private PdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(string $form2307Id, string $actorId): string
    {
        $row = DB::table('tax.form_2307')->where('id', $form2307Id)->first();

        if (! $row) {
            throw new RuntimeException("Form 2307 {$form2307Id} not found.");
        }

        $pdfPath = $this->pdf->renderForm2307($form2307Id);

        DB::table('tax.form_2307')
            ->where('id', $form2307Id)
            ->update(['pdf_path' => $pdfPath, 'updated_at' => now()]);

        $this->audit->writeEvent(
            actorId:     $actorId,
            companyId:   $row->company_id,
            eventType:   'form2307.pdf_rendered',
            aggregate:   'Form2307',
            aggregateId: $form2307Id,
            payload:     ['pdf_path' => $pdfPath],
        );

        return $pdfPath;
    }
}
