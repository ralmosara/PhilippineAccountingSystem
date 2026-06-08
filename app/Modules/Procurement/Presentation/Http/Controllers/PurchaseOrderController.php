<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Controllers;

use App\Modules\Procurement\Infrastructure\Persistence\Eloquent\PurchaseOrderModel;
use App\Modules\Procurement\Presentation\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Purchase order resource — 7 RESTful methods. */
final class PurchaseOrderController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrderModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->string('vendor_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return PurchaseOrderResource::collection(
            $query->orderByDesc('order_date')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $po): PurchaseOrderResource
    {
        return new PurchaseOrderResource(
            PurchaseOrderModel::query()
                ->with('lines')
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($po)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse(['statuses' => ['draft', 'approved', 'sent', 'partial', 'fulfilled', 'cancelled']]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'CreatePurchaseOrder action pending implementation.'], 501);
    }

    public function edit(Request $request, string $po): JsonResponse
    {
        return new JsonResponse(['purchase_order' => $this->show($request, $po)]);
    }

    public function update(Request $request, string $po): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdatePurchaseOrder action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $po): JsonResponse
    {
        // Only draft POs can be deleted; approved POs must be cancelled
        $deleted = PurchaseOrderModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $po)
            ->where('status', 'draft')
            ->delete();

        return new JsonResponse(null, $deleted ? 204 : 423);
    }
}
