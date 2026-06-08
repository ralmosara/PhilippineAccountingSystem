<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm1702RT;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateAnnualITRRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/tax-forms/1702rt/generate  (MFA) */
final class GenerateForm1702RTController
{
    public function __invoke(
        GenerateAnnualITRRequest $request,
        GenerateForm1702RT $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId:             $request->user()->company_id,
            year:                  $request->integer('year'),
            actorId:               $request->user()->id,
            priorExcessCredits:    $request->string('prior_excess_credits', '0.00')->toString(),
            creditableWtOverride:  $request->string('creditable_wt', '0.00')->toString(),
            taxPaymentsToDate:     $request->string('quarterly_payments', '0.00')->toString(),
            useOsd:                $request->boolean('use_osd'),
        );

        $model = BirFormModel::with(['lines'])->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
