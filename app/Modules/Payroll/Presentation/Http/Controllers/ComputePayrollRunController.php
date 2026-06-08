<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\ComputePayrollRun;
use App\Modules\Payroll\Domain\ValueObjects\PayrollFrequency;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayrollRunModel;
use App\Modules\Payroll\Presentation\Http\Requests\ComputePayrollRunRequest;
use App\Modules\Payroll\Presentation\Http\Resources\PayrollRunResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/payroll/runs/compute */
final class ComputePayrollRunController
{
    public function __invoke(
        ComputePayrollRunRequest $request,
        ComputePayrollRun $action,
    ): JsonResponse {
        $run = $action->execute(
            companyId:        $request->user()->company_id,
            payrollPeriodId:  $request->string('payroll_period_id')->toString(),
            periodStart:      new DateTimeImmutable($request->string('period_start')->toString()),
            periodEnd:        new DateTimeImmutable($request->string('period_end')->toString()),
            frequency:        PayrollFrequency::from($request->string('frequency')->toString()),
            runType:          $request->string('run_type', 'regular')->toString(),
            actorId:          $request->user()->id,
        );

        $model = PayrollRunModel::with('payslips.lines')->findOrFail($run->id->value);
        return new JsonResponse(new PayrollRunResource($model), 201);
    }
}
