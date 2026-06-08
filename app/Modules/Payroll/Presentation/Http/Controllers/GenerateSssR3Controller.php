<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\GenerateStatutoryRemittance;
use App\Modules\Payroll\Domain\ValueObjects\StatutoryAgency;
use App\Modules\Payroll\Presentation\Http\Requests\GenerateStatutoryRemittanceRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/payroll/remittances/sss-r3/generate  (MFA-gated) */
final class GenerateSssR3Controller
{
    public function __invoke(
        GenerateStatutoryRemittanceRequest $request,
        GenerateStatutoryRemittance $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId:  (string) $request->user()->company_id,
            agency:     StatutoryAgency::Sss,
            periodFrom: new DateTimeImmutable((string) $request->input('period_from')),
            periodTo:   new DateTimeImmutable((string) $request->input('period_to')),
            actorId:    (string) $request->user()->id,
        );

        // file_body is the raw CSV — clients usually want metadata + download URL,
        // but we ship the body inline as well so test fixtures can byte-compare.
        return new JsonResponse($result, 201);
    }
}
