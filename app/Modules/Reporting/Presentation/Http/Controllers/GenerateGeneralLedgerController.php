<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Actions\GenerateGeneralLedger;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;
use App\Modules\Reporting\Presentation\Http\Requests\GeneralLedgerRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class GenerateGeneralLedgerController
{
    public function __invoke(
        GeneralLedgerRequest $request,
        GenerateGeneralLedger $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->company_id,
            period:    ReportPeriod::forPeriod(
                new DateTimeImmutable($request->string('from')->toString()),
                new DateTimeImmutable($request->string('to')->toString()),
            ),
            actorId:   $request->user()->id,
            accountId: $request->input('account_id'),
        );

        return new JsonResponse([
            'id'           => $result['id'],
            'pdf_path'     => $result['pdf_path'],
            'ledger_count' => $result['payload']['ledger_count'] ?? 0,
            'account_id'   => $result['payload']['account_id'] ?? null,
        ], 201);
    }
}
