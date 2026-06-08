<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayrollRunModel;
use App\Modules\Payroll\Presentation\Http\Resources\PayrollRunResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Payroll run resource — 7 RESTful methods (read-only ops; compute/approve are single-actions). */
final class PayrollRunController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PayrollRunModel::query()->with('payslips');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('year')) {
            $query->where('run_no', 'like', '%-'.$request->string('year').'-%');
        }

        return PayrollRunResource::collection(
            $query->orderByDesc('computed_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $run): PayrollRunResource
    {
        return new PayrollRunResource(
            PayrollRunModel::query()->with('payslips.lines')->findOrFail($run)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use POST /payroll/runs/compute to compute a payroll run.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'Use POST /payroll/runs/compute.'], 405);
    }

    public function edit(Request $request, string $run): JsonResponse
    {
        return new JsonResponse(['message' => 'Payroll runs are not editable; create an adjustment run.'], 423);
    }

    public function update(Request $request, string $run): JsonResponse
    {
        return new JsonResponse(['message' => 'Payroll runs are not editable.'], 423);
    }

    public function destroy(Request $request, string $run): JsonResponse
    {
        // Only draft runs can be deleted
        $deleted = PayrollRunModel::query()
            ->where('id', $run)
            ->where('status', 'draft')
            ->delete();

        return new JsonResponse(null, $deleted ? 204 : 423);
    }
}
