<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\Generate13thMonthRun;
use App\Modules\Payroll\Presentation\Http\Requests\Generate13thMonthRunRequest;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/payroll-runs/13th-month/generate
 *
 * Generates the annual 13th month pay run for the company (PD 851).
 * Idempotent — returns the existing run if one is already computed for
 * the requested year. MFA-gated because it triggers a financial commitment.
 */
final class Generate13thMonthRunController
{
    public function __invoke(
        Generate13thMonthRunRequest $request,
        Generate13thMonthRun $action,
    ): JsonResponse {
        $run = $action->execute(
            companyId: (string) $request->user()->company_id,
            year:      $request->year(),
            actorId:   (string) $request->user()->id,
        );

        // Return flat summary; the run is inspectable in full via GET /payroll-runs/{run}
        $totalGross = '0.00';
        $totalNet   = '0.00';
        foreach ($run->payslips as $p) {
            $totalGross = bcadd($totalGross, $p->grossCompensation->toPhp(), 2);
            $totalNet   = bcadd($totalNet,   $p->netPay->toPhp(), 2);
        }

        $year = $request->year();

        return new JsonResponse([
            'id'            => $run->id->value,
            'run_no'        => $run->runNo,
            'run_type'      => $run->runType,
            'status'        => $run->status,
            'period_start'  => "{$year}-12-01",
            'period_end'    => "{$year}-12-24",
            'payslip_count' => count($run->payslips),
            'total_gross'   => $totalGross,
            'total_net'     => $totalNet,
        ], 201);
    }
}
