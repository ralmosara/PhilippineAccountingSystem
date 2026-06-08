<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Controllers;

use App\Modules\Accounting\Application\Actions\LockFiscalPeriod;
use App\Modules\Accounting\Presentation\Http\Requests\LockFiscalPeriodRequest;
use Illuminate\Http\JsonResponse;

final class LockFiscalPeriodController
{
    public function __invoke(
        LockFiscalPeriodRequest $request,
        LockFiscalPeriod $action,
        string $period,
    ): JsonResponse {
        $action->execute(
            fiscalPeriodId: $period,
            companyId:      $request->user()->company_id,
            actorId:        $request->user()->id,
            reason:         $request->input('reason'),
        );

        return new JsonResponse(['message' => 'Period locked.'], 200);
    }
}
