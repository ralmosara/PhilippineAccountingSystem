<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Infrastructure\Persistence\Eloquent\OsdElectionModel;
use App\Modules\Tax\Presentation\Http\Resources\OsdElectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only resource over tax.osd_elections. Elections are CREATED by the
 * ITR generator actions (1701Q/1702Q/1701/1702RT), so this controller
 * exposes only the read RESTful methods. The mutator (supersede) lives in
 * a single-action invokable controller per Taylor Otwell convention.
 *
 * `store` / `update` / `destroy` return 405 — the resource is system-managed.
 */
final class OsdElectionController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $request->user()->company_id;
        $q = OsdElectionModel::query()->where('company_id', $companyId);

        if ($request->filled('fiscal_year')) {
            $q->where('fiscal_year', $request->integer('fiscal_year'));
        }
        if ($request->filled('taxpayer_type')) {
            $q->where('taxpayer_type', $request->string('taxpayer_type'));
        }
        if ($request->boolean('active_only')) {
            $q->whereNull('superseded_at');
        }

        return OsdElectionResource::collection(
            $q->orderByDesc('fiscal_year')
              ->orderBy('taxpayer_type')
              ->orderByDesc('locked_at')
              ->paginate($request->integer('per_page', 25)),
        );
    }

    public function show(Request $request, string $election): OsdElectionResource
    {
        return new OsdElectionResource(
            OsdElectionModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($election),
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'OSD elections are created automatically by 1701Q / 1702Q / 1701 / 1702-RT generation. '
                .'Use the supersede endpoint to amend an existing election.',
        ]);
    }

    public function store(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'OSD elections cannot be created directly. '
                .'Generate a quarterly or annual ITR with the desired regime instead.',
        ], 405);
    }

    public function edit(Request $request, string $election): OsdElectionResource
    {
        // Mirror show — the React edit form pre-populates from this.
        return $this->show($request, $election);
    }

    public function update(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'OSD elections are immutable. '
                .'To change the regime, POST /osd-elections/{election}/supersede.',
        ], 423);
    }

    public function destroy(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'OSD elections cannot be deleted (BIR audit trail). '
                .'Supersede with a documented amendment instead.',
        ], 423);
    }
}
