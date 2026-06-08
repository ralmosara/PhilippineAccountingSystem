<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm2550M;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateMonthlyFormRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

final class GenerateForm2550MController
{
    public function __invoke(
        GenerateMonthlyFormRequest $request,
        GenerateForm2550M $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId: $request->user()->company_id,
            year:      $request->integer('year'),
            month:     $request->integer('month'),
            actorId:   $request->user()->id,
        );

        $model = BirFormModel::with(['lines', 'alphalistEntries'])->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
