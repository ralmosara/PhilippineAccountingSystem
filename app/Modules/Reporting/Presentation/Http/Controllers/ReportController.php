<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Infrastructure\Persistence\Eloquent\ReportRunModel;
use App\Modules\Reporting\Presentation\Http\Resources\ReportRunResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Read-only resource — generated reports list/detail. Generation is via single-actions. */
final class ReportController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ReportRunModel::query()
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->string('report_type'));
        }
        if ($request->filled('from')) {
            $query->where('period_from', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('period_to', '<=', $request->date('to'));
        }

        return ReportRunResource::collection(
            $query->orderByDesc('generated_at')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $report): ReportRunResource
    {
        return new ReportRunResource(
            ReportRunModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($report)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use POST /reports/trial-balance, /reports/balance-sheet, or /reports/income-statement.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'Use the report-specific generators.'], 405);
    }

    public function edit(Request $request, string $report): JsonResponse
    {
        return new JsonResponse(['message' => 'Reports are immutable; regenerate to refresh.'], 423);
    }

    public function update(Request $request, string $report): JsonResponse
    {
        return new JsonResponse(['message' => 'Reports are immutable.'], 423);
    }

    public function destroy(Request $request, string $report): JsonResponse
    {
        ReportRunModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $report)
            ->delete();
        return new JsonResponse(null, 204);
    }
}
