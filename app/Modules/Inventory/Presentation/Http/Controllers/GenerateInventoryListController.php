<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Modules\Inventory\Application\Actions\GenerateInventoryList;
use App\Modules\Inventory\Presentation\Http\Requests\GenerateInventoryListRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 *   POST /api/v1/inventory/list/generate    body: { year: 2026 }
 *
 * Generates the BIR Annual Inventory List (RMC 57-2015 / RR 1-2018).
 * Output: CSV file in MinIO + audit event.
 */
final class GenerateInventoryListController
{
    public function __invoke(
        GenerateInventoryListRequest $request,
        GenerateInventoryList $action,
    ): JsonResponse {
        $year = $request->integer('year');
        $asOfDate = $request->filled('as_of_date')
            ? CarbonImmutable::parse($request->string('as_of_date')->toString())
            : CarbonImmutable::create($year, 12, 31);

        $result = $action->execute(
            companyId: $request->user()->company_id,
            year:      $year,
            asOfDate:  $asOfDate->toDateTimeImmutable(),
            actorId:   $request->user()->id,
        );

        return new JsonResponse($result, 201);
    }
}
