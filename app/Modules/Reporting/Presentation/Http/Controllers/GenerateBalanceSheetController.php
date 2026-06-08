<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateBalanceSheet;
use App\Modules\Reporting\Presentation\Http\Requests\AsOfReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/reports/balance-sheet */
final class GenerateBalanceSheetController
{
    public function __invoke(
        AsOfReportRequest $request,
        GenerateBalanceSheet $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->company_id,
            asOfDate:  new DateTimeImmutable($request->string('as_of_date')->toString()),
            actorId:   $request->user()->id,
        );

        $bs = $result['balance_sheet'];

        return new JsonResponse([
            'id'                       => $result['id'],
            'pdf_path'                 => $result['pdf_path'],
            'as_of_date'               => $bs->period->to->format('Y-m-d'),
            'total_assets'             => $bs->totalAssets,
            'total_liabilities'        => $bs->totalLiabilities,
            'total_equity'             => $bs->totalEquity,
            'current_year_earnings'    => $bs->currentYearEarnings,
            'liabilities_and_equity'   => $bs->totalLiabilitiesAndEquity(),
            'is_balanced'              => $bs->isBalanced(),
        ], 201);
    }
}
