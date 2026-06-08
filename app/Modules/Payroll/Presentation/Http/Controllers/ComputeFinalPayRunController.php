<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\ComputeFinalPayRun;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayrollRunModel;
use App\Modules\Payroll\Presentation\Http\Requests\ComputeFinalPayRunRequest;
use App\Modules\Payroll\Presentation\Http\Resources\PayrollRunResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/payroll-runs/final-pay/compute
 *
 * Computes a Final Pay (separation pay) run for a single employee
 * per RA 7641 / Labor Code Art. 302.
 *
 * Returns the created PayrollRun resource (status 201).
 * Authorization is gated by the `payroll.runs.compute` permission
 * (enforced inside ComputeFinalPayRunRequest::authorize()).
 */
final class ComputeFinalPayRunController
{
    public function __invoke(
        ComputeFinalPayRunRequest $request,
        ComputeFinalPayRun $action,
    ): JsonResponse {
        $run = $action->execute(
            employeeId:        $request->string('employee_id')->toString(),
            companyId:         (string) $request->user()->company_id,
            separationDate:    $request->string('separation_date')->toString(),
            separationReason:  $request->string('separation_reason')->toString(),
            actorId:           (string) $request->user()->id,
        );

        $model = PayrollRunModel::with('payslips.lines')->findOrFail($run->id->value);

        return new JsonResponse(new PayrollRunResource($model), 201);
    }
}
