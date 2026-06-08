<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateCashDisbursementsBook;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use App\Modules\Reporting\Presentation\Http\Requests\PeriodReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class GenerateCashDisbursementsBookController
{
    public function __invoke(
        PeriodReportRequest $request,
        GenerateCashDisbursementsBook $action,
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
            'total'     => $result['payload']['total'] ?? '0.00',
            'by_method' => $result['payload']['by_method'] ?? [],
        ], 201);
    }
}
