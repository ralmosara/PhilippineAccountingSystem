<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm1604E;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateAnnualReturnRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/tax-forms/1604e/generate  (MFA — annual EWT alphalist) */
final class GenerateForm1604EController
{
    public function __invoke(
        GenerateAnnualReturnRequest $request,
        GenerateForm1604E $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId: $request->user()->company_id,
            year:      $request->integer('year'),
            actorId:   $request->user()->id,
        );

        $model = BirFormModel::with(['lines', 'alphalistEntries'])->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
