<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateEquityStatement;
use App\Modules\Reporting\Presentation\Http\Requests\PeriodReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/reports/equity-statement */
final class GenerateEquityStatementController
{
    public function __invoke(
        PeriodReportRequest $request,
        GenerateEquityStatement $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->company_id,
            from:      new DateTimeImmutable($request->string('from')->toString()),
            to:        new DateTimeImmutable($request->string('to')->toString()),
            actorId:   $request->user()->id,
        );

        $es = $result['equity_statement'];

        return new JsonResponse([
            'id'                     => $result['id'],
            'pdf_path'               => $result['pdf_path'],
            'period'                 => $es->period->label(),
            'total_beginning'        => $es->totalBeginning,
            'total_movement'         => $es->totalMovement,
            'total_ending'           => $es->totalEnding,
            'net_income_for_period'  => $es->netIncomeForPeriod,
            'total_equity'           => $es->totalEquity(),
            'account_count'          => count($es->accounts),
        ], 201);
    }
}
