<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\StockMovementModel;
use App\Modules\Inventory\Presentation\Http\Resources\StockMovementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Stock movement resource — read-only ops only (no writes via this controller).
 * Movements are recorded via:
 *   - RecordStockMovementController (single-action), or
 *   - Auto-listeners on InvoiceIssued / VendorBillPosted
 */
final class StockMovementController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StockMovementModel::query()->with(['item', 'warehouse']);

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->string('item_id'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->string('warehouse_id'));
        }
        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->string('movement_type'));
        }
        if ($request->filled('from')) {
            $query->where('moved_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('moved_at', '<=', $request->date('to'));
        }

        return StockMovementResource::collection(
            $query->orderByDesc('moved_at')->paginate($request->integer('per_page', 50))
        );
    }

    public function show(Request $request, string $movement): StockMovementResource
    {
        return new StockMovementResource(
            StockMovementModel::query()->with(['item', 'warehouse'])->findOrFail($movement)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse(['message' => 'Use POST /stock-movements/record.']);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'Use POST /stock-movements/record.'], 405);
    }

    public function edit(Request $request, string $movement): JsonResponse
    {
        return new JsonResponse(['message' => 'Stock movements are immutable.'], 423);
    }

    public function update(Request $request, string $movement): JsonResponse
    {
        return new JsonResponse(['message' => 'Stock movements are immutable.'], 423);
    }

    public function destroy(Request $request, string $movement): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Stock movements cannot be deleted; record a reverse adjustment.',
        ], 423);
    }
}
