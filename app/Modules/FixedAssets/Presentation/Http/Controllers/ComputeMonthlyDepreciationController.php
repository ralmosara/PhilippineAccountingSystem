<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Presentation\Http\Controllers;

use App\Modules\FixedAssets\Application\Actions\ComputeMonthlyDepreciation;
use App\Modules\FixedAssets\Presentation\Http\Requests\ComputeMonthlyDepreciationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Single-action controller — POST /fixed-assets/depreciation/compute
 *
 * MFA-gated. Runs the monthly depreciation batch for the authenticated user's
 * company for the requested year/month.
 */
final class ComputeMonthlyDepreciationController
{
    public function __invoke(
        ComputeMonthlyDepreciationRequest $request,
        ComputeMonthlyDepreciation        $action,
    ): JsonResponse {
        $result = $action->execute(
            companyId: $request->user()->companyId,
            year:      (int) $request->validated('year'),
            month:     (int) $request->validated('month'),
            actorId:   $request->user()->id,
        );

        return new JsonResponse([
            'message' => 'Monthly depreciation computed successfully.',
            'data'    => $result,
        ]);
    }
}
