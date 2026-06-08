<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\GenerateForm1701;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Requests\GenerateAnnualITRRequest;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/tax-forms/1701/generate  (MFA) */
final class GenerateForm1701Controller
{
    public function __invoke(
        GenerateAnnualITRRequest $request,
        GenerateForm1701 $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId:             $request->user()->company_id,
            year:                  $request->integer('year'),
            actorId:               $request->user()->id,
            electFlat8Percent:     $request->boolean('elect_flat_8pct'),
            personalExemption:     $request->string('personal_exemption', '0.00')->toString(),
            priorExcessCredits:    $request->string('prior_excess_credits', '0.00')->toString(),
            creditableWtOverride:  $request->string('creditable_wt', '0.00')->toString(),
            taxPaymentsToDate:     $request->string('quarterly_payments', '0.00')->toString(),
            useOsd:                $request->boolean('use_osd'),
        );

        $model = BirFormModel::with(['lines'])->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
