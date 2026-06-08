<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Domain\Entities\BirForm;

/**
 * Renders a BIR form to PDF. Implementations can use DomPDF (simple,
 * no external dep) or Browsershot (Puppeteer, pixel-perfect from HTML).
 */
interface PdfRendererContract
{
    /** Returns the storage path (MinIO key) of the rendered PDF. */
    public function render(BirForm $form): string;

    /**
     * Renders a Form 2307 from a single tax.form_2307 row.
     * Different layout from the multi-page returns; needs its own template.
     */
    public function renderForm2307(string $form2307Id): string;
}
