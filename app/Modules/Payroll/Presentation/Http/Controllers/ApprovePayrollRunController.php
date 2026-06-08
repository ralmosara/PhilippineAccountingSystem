<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\ApprovePayrollRun;
use App\Modules\Payroll\Application\Exceptions\PayrollAlreadyApprovedException;
use App\Modules\Payroll\Application\Exceptions\PayrollRunNotFoundException;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayrollRunModel;
use App\Modules\Payroll\Presentation\Http\Requests\ApprovePayrollRunRequest;
use App\Modules\Payroll\Presentation\Http\Resources\PayrollRunResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/payroll/runs/{run}/approve  (MFA required) */
final class ApprovePayrollRunController
{
    public function __invoke(
        ApprovePayrollRunRequest $request,
        ApprovePayrollRun $action,
        string $run,
    ): JsonResponse {
        try {
            $approved = $action->execute(
                payrollRunId: $run,
                companyId:    $request->user()->company_id,
                accounts:     $request->validated('accounts'),
                actorId:      $request->user()->id,
            );
        } catch (PayrollRunNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (PayrollAlreadyApprovedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }

        $model = PayrollRunModel::with('payslips.lines')->findOrFail($approved->id->value);
        return new JsonResponse(new PayrollRunResource($model), 200);
    }
}
