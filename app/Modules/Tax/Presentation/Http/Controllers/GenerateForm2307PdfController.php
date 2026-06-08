<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm2307Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   POST /api/v1/tax-forms/2307/{form2307}/render
 * Renders (or re-renders) the PDF for an existing tax.form_2307 row.
 */
final class GenerateForm2307PdfController
{
    public function __invoke(
        Request $request,
        GenerateForm2307Pdf $action,
        string $form2307,
    ): JsonResponse {
        try {
            $pdfPath = $action->execute(
                form2307Id: $form2307,
                actorId:    $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        }

        return new JsonResponse(['pdf_path' => $pdfPath], 200);
    }
}
