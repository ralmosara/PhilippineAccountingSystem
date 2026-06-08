<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GeneratePurchasesBook;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use App\Modules\Reporting\Presentation\Http\Requests\PeriodReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class GeneratePurchasesBookController
{
    public function __invoke(
        PeriodReportRequest $request,
        GeneratePurchasesBook $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->company_id,
            period:    ReportPeriod::forPeriod(
                new DateTimeImmutable($request->string('from')->toString()),
                new DateTimeImmutable($request->string('to')->toString()),
            ),
            actorId:   $request->user()->id,
        );

        return new JsonResponse([
            'id'        => $result['id'],
            'pdf_path'  => $result['pdf_path'],
            'row_count' => $result['payload']['row_count'] ?? 0,
            'totals'    => $result['payload']['totals'] ?? null,
        ], 201);
    }
}
