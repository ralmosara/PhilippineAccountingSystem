<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\ExportSawtDat;
use App\Modules\Tax\Application\Exceptions\FormNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   POST /api/v1/tax-forms/{form}/export-sawt
 * Exports the SAWT DAT attachment for a generated 1601-EQ.
 */
final class ExportSawtDatController
{
    public function __invoke(
        Request $request,
        ExportSawtDat $action,
        string $form,
    ): JsonResponse {
        try {
            $datPath = $action->execute(
                birFormId: $form,
                actorId:   $request->user()->id,
            );
        } catch (FormNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(['dat_path' => $datPath], 200);
    }
}
