<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm1604CF;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateAnnualReturnRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/tax-forms/1604cf/generate  (MFA — annual comp WT alphalist) */
final class GenerateForm1604CFController
{
    public function __invoke(
        GenerateAnnualReturnRequest $request,
        GenerateForm1604CF $action,
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
