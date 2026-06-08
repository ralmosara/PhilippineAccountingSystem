<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateTrialBalance;
use App\Modules\Reporting\Presentation\Http\Requests\PeriodReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/reports/trial-balance */
final class GenerateTrialBalanceController
{
    public function __invoke(
        PeriodReportRequest $request,
        GenerateTrialBalance $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->company_id,
            from:      new DateTimeImmutable($request->string('from')->toString()),
            to:        new DateTimeImmutable($request->string('to')->toString()),
            actorId:   $request->user()->id,
        );

        return new JsonResponse([
            'id'           => $result['id'],
            'pdf_path'     => $result['pdf_path'],
            'is_balanced'  => $result['trial_balance']->isBalanced(),
            'total_debit'  => $result['trial_balance']->totalDebit,
            'total_credit' => $result['trial_balance']->totalCredit,
            'line_count'   => count($result['trial_balance']->lines),
        ], 201);
    }
}
