<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\ExportAlphalistDat;
use App\Modules\Tax\Application\Exceptions\FormNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   POST /api/v1/tax-forms/{form}/export-alphalist
 *
 * Generic alphalist DAT exporter for annual returns (1604-CF / 1604-E).
 * Distinct from the SAWT exporter which is quarterly-specific.
 */
final class ExportAlphalistDatController
{
    public function __invoke(
        Request $request,
        ExportAlphalistDat $action,
        string $form,
    ): JsonResponse {
        try {
            $datPath = $action->execute(birFormId: $form, actorId: $request->user()->id);
        } catch (FormNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(['dat_path' => $datPath], 200);
    }
}
