<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Controllers;

use App\Modules\Procurement\Application\Actions\CreateVendor;
use App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorModel;
use App\Modules\Procurement\Presentation\Http\Requests\StoreVendorRequest;
use App\Modules\Procurement\Presentation\Http\Resources\VendorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Vendor resource — only the 7 RESTful methods. */
final class VendorController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = VendorModel::query()->where('company_id', $request->user()->company_id);

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q
                ->where('registered_name', 'ilike', $term)
                ->orWhere('vendor_no', 'ilike', $term)
                ->orWhere('tin', 'ilike', $term));
        }
        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return VendorResource::collection(
            $query->orderBy('registered_name')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $vendor): VendorResource
    {
        return new VendorResource(
            VendorModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($vendor)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'common_atc_codes' => ['WC010', 'WC020', 'WC100', 'WI010', 'WI011', 'WI070', 'WI080'],
        ]);
    }

    public function store(StoreVendorRequest $request, CreateVendor $action): JsonResponse
    {
        $vendor = $action->execute(
            companyId: $request->user()->company_id,
            data:      $request->validated(),
            actorId:   $request->user()->id,
        );

        $model = VendorModel::findOrFail($vendor->id->value);
        return new JsonResponse(new VendorResource($model), 201);
    }

    public function edit(Request $request, string $vendor): VendorResource
    {
        return $this->show($request, $vendor);
    }

    public function update(Request $request, string $vendor): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdateVendor action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $vendor): JsonResponse
    {
        VendorModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $vendor)
            ->update(['is_active' => false]);
        return new JsonResponse(null, 204);
    }
}
