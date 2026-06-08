<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm1601EQ;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateQuarterlyFormRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

final class GenerateForm1601EQController
{
    public function __invoke(
        GenerateQuarterlyFormRequest $request,
        GenerateForm1601EQ $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId: $request->user()->company_id,
            year:      $request->integer('year'),
            quarter:   $request->integer('quarter'),
            actorId:   $request->user()->id,
        );

        $model = BirFormModel::with(['lines', 'alphalistEntries'])->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
