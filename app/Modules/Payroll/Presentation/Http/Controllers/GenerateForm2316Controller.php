<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\GenerateForm2316;
use App\Modules\Payroll\Presentation\Http\Requests\GenerateForm2316Request;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/payroll/forms/2316/generate   (MFA — sensitive PII) */
final class GenerateForm2316Controller
{
    public function __invoke(
        GenerateForm2316Request $request,
        GenerateForm2316 $action,
    ): JsonResponse {
        $form = $action->execute(
            companyId:  $request->user()->company_id,
            employeeId: $request->string('employee_id')->toString(),
            year:       $request->integer('year'),
            actorId:    $request->user()->id,
        );

        $model = BirFormModel::with('lines')->findOrFail($form->id->value);
        return new JsonResponse(new BirFormResource($model), 201);
    }
}
