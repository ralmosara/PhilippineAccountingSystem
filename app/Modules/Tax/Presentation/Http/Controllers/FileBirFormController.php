<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\FileBirForm;
use App\Modules\Tax\Application\Exceptions\FormAlreadyFiledException;
use App\Modules\Tax\Application\Exceptions\FormNotFoundException;
use App\Modules\Tax\Presentation\Http\Requests\FileBirFormRequest;
use Illuminate\Http\JsonResponse;

/**
 *   POST /api/v1/tax-forms/{form}/file
 * Records that a BIR form has been filed (via eBIRForms / EFPS / manual).
 */
final class FileBirFormController
{
    public function __invoke(
        FileBirFormRequest $request,
        FileBirForm $action,
        string $form,
    ): JsonResponse {
        try {
            $action->execute(
                birFormId:    $form,
                birFilingRef: $request->string('bir_filing_ref')->toString(),
                channel:      $request->string('channel')->toString(),
                actorId:      $request->user()->id,
            );
        } catch (FormNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (FormAlreadyFiledException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }

        return new JsonResponse(['message' => 'Form filed.'], 200);
    }
}
