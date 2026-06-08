<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\WarehouseModel;
use App\Modules\Inventory\Presentation\Http\Resources\WarehouseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Warehouse resource — 7 RESTful methods. */
final class WarehouseController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return WarehouseResource::collection(
            WarehouseModel::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $warehouse): WarehouseResource
    {
        return new WarehouseResource(
            WarehouseModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($warehouse)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse(['message' => 'POST a warehouse with code, name, address.']);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'CreateWarehouse action pending implementation.'], 501);
    }

    public function edit(Request $request, string $warehouse): WarehouseResource
    {
        return $this->show($request, $warehouse);
    }

    public function update(Request $request, string $warehouse): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdateWarehouse action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $warehouse): JsonResponse
    {
        WarehouseModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $warehouse)
            ->update(['is_active' => false]);

        return new JsonResponse(null, 204);
    }
}
