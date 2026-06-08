<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateGeneralJournal;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use App\Modules\Reporting\Presentation\Http\Requests\PeriodReportRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class GenerateGeneralJournalController
{
    public function __invoke(
        PeriodReportRequest $request,
        GenerateGeneralJournal $action,
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
            'id'           => $result['id'],
            'pdf_path'     => $result['pdf_path'],
            'entry_count'  => $result['payload']['entry_count'] ?? 0,
            'total_debit'  => $result['payload']['total_debit']  ?? '0.00',
            'total_credit' => $result['payload']['total_credit'] ?? '0.00',
            'is_balanced'  => $result['payload']['is_balanced']  ?? false,
        ], 201);
    }
}
