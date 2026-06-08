<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\BulkImportForm2307Received;
use App\Modules\Tax\Presentation\Http\Requests\BulkImportForm2307Request;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * POST /api/v1/tax/form-2307-received/bulk-import
 *
 * Imports many 2307 certificates from a CSV. Returns a per-row report so
 * the operator can address only the failing rows on the next upload.
 *
 * HTTP status decoded by outcome:
 *   - 201 if at least one row landed
 *   - 422 if every row failed parsing or validation
 *   - 400 if the CSV header is malformed (parser refused entirely)
 */
final class BulkImportForm2307ReceivedController
{
    public function __invoke(
        BulkImportForm2307Request $request,
        BulkImportForm2307Received $action,
    ): JsonResponse {
        try {
            $report = $action->executeFromCsv(
                csvContent: $request->csvContent(),
                companyId:  (string) $request->user()->company_id,
                recordedBy: (string) $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            // Structural CSV failure (bad header, empty body) — no rows processed.
            return new JsonResponse(['error' => 'csv_format', 'message' => $e->getMessage()], 400);
        }

        $body = [
            'summary' => [
                'total'      => $report->totalRows,
                'successful' => $report->successCount(),
                'duplicates' => $report->duplicateCount(),
                'failed'     => $report->failedCount(),
            ],
            'successful' => $report->successful,
            'duplicates' => $report->duplicates,
            'failed'     => $report->failed,
        ];

        $status = $report->hasAnySuccess() ? 201 : 422;
        return new JsonResponse($body, $status);
    }
}
