<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateIncomeStatement;
use App\Modules\Reporting\Presentation\Http\Requests\PeriodReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/reports/income-statement */
final class GenerateIncomeStatementController
{
    public function __invoke(
        PeriodReportRequest $request,
        GenerateIncomeStatement $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->company_id,
            from:      new DateTimeImmutable($request->string('from')->toString()),
            to:        new DateTimeImmutable($request->string('to')->toString()),
            actorId:   $request->user()->id,
        );

        $is = $result['income_statement'];

        return new JsonResponse([
            'id'                  => $result['id'],
            'pdf_path'            => $result['pdf_path'],
            'period'              => $is->period->label(),
            'total_revenue'       => $is->totalRevenue,
            'total_cost_of_sales' => $is->totalCostOfSales,
            'gross_profit'        => $is->grossProfit(),
            'operating_income'    => $is->operatingIncome(),
            'income_before_tax'   => $is->incomeBeforeTax(),
            'total_income_tax'    => $is->totalIncomeTax,
            'net_income'          => $is->netIncome(),
        ], 201);
    }
}
