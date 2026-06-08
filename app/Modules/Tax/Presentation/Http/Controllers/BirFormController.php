<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use App\Modules\Tax\Presentation\Http\Resources\BirFormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** BIR forms resource — 7 RESTful methods only.  All form generation lives in single-action controllers. */
final class BirFormController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BirFormModel::query()
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('form_type')) {
            $query->where('form_type', $request->string('form_type'));
        }
        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return BirFormResource::collection(
            $query->orderByDesc('period_to')->orderBy('form_type')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $form): BirFormResource
    {
        return new BirFormResource(
            BirFormModel::query()
                ->with(['lines', 'alphalistEntries'])
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($form)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use POST /tax-forms/2550m/generate, /tax-forms/2550q/generate, etc.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use the form-specific generator endpoints.',
        ], 405);
    }

    public function edit(Request $request, string $form): JsonResponse
    {
        return new JsonResponse(['message' => 'Generated BIR forms are not editable; regenerate to recompute.'], 423);
    }

    public function update(Request $request, string $form): JsonResponse
    {
        return new JsonResponse(['message' => 'Generated BIR forms are not editable.'], 423);
    }

    public function destroy(Request $request, string $form): JsonResponse
    {
        // Only draft forms can be deleted
        $deleted = BirFormModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $form)
            ->where('status', 'draft')
            ->delete();

        return new JsonResponse(null, $deleted ? 204 : 423);
    }
}
