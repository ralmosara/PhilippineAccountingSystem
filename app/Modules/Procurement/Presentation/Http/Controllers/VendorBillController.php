<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Controllers;

use App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorBillModel;
use App\Modules\Procurement\Presentation\Http\Resources\VendorBillResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Vendor bill resource — 7 RESTful methods.
 * Posting / voiding live in dedicated single-action controllers.
 */
final class VendorBillController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = VendorBillModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->string('vendor_id'));
        }
        if ($request->filled('match_status')) {
            $query->where('match_status', $request->string('match_status'));
        }
        if ($request->boolean('posted_only')) {
            $query->whereNotNull('posted_at');
        }

        return VendorBillResource::collection(
            $query->orderByDesc('bill_date')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $bill): VendorBillResource
    {
        return new VendorBillResource(
            VendorBillModel::query()
                ->with(['lines', 'vendor'])
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($bill)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse(['message' => 'Use POST /vendor-bills/post to create + post.']);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use POST /vendor-bills/post (single-action that posts + auto-issues 2307).',
        ], 405);
    }

    public function edit(Request $request, string $bill): JsonResponse
    {
        return new JsonResponse(['message' => 'Posted vendor bills cannot be edited.'], 423);
    }

    public function update(Request $request, string $bill): JsonResponse
    {
        return new JsonResponse(['message' => 'Posted vendor bills cannot be edited.'], 423);
    }

    public function destroy(Request $request, string $bill): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Vendor bills cannot be deleted; use POST /vendor-bills/{id}/void.',
        ], 423);
    }
}
