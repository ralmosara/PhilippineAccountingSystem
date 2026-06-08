<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm1702Q;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateQuarterlyITRRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/tax-forms/1702q/generate  (MFA) */
final class GenerateForm1702QController
{
    public function __invoke(
        GenerateQuarterlyITRRequest $request,
        GenerateForm1702Q $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId:             $request->user()->company_id,
            year:                  $request->integer('year'),
            quarter:               $request->integer('quarter'),
            actorId:               $request->user()->id,
            useOsd:                $request->boolean('use_osd'),
            creditableWtOverride:  $request->string('creditable_wt', '0.00')->toString(),
        );

        $model = BirFormModel::with(['lines'])->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
