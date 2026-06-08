<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateCashFlowStatement;
use App\Modules\Reporting\Presentation\Http\Requests\PeriodReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/reports/cash-flow */
final class GenerateCashFlowStatementController
{
    public function __invoke(
        PeriodReportRequest $request,
        GenerateCashFlowStatement $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->company_id,
            from:      new DateTimeImmutable($request->string('from')->toString()),
            to:        new DateTimeImmutable($request->string('to')->toString()),
            actorId:   $request->user()->id,
        );

        $cf = $result['cash_flow'];

        return new JsonResponse([
            'id'              => $result['id'],
            'pdf_path'        => $result['pdf_path'],
            'period'          => $cf->period->label(),
            'total_operating' => $cf->totalOperating,
            'total_investing' => $cf->totalInvesting,
            'total_financing' => $cf->totalFinancing,
            'net_change'      => $cf->netChange(),
            'beginning_cash'  => $cf->beginningCash,
            'ending_cash'     => $cf->endingCash,
            'reconciles'      => $cf->reconciles(),
        ], 201);
    }
}
