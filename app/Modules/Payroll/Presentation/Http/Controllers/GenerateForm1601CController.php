<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\GenerateForm1601C;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateMonthlyFormRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/payroll/forms/1601c/generate  (MFA) */
final class GenerateForm1601CController
{
    public function __invoke(
        GenerateMonthlyFormRequest $request,
        GenerateForm1601C $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId: $request->user()->company_id,
            year:      $request->integer('year'),
            month:     $request->integer('month'),
            actorId:   $request->user()->id,
        );

        $model = BirFormModel::with('lines')->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
