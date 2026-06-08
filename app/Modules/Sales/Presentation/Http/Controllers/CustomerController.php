<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Modules\Sales\Application\Actions\CreateCustomer;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\CustomerModel;
use App\Modules\Sales\Presentation\Http\Requests\StoreCustomerRequest;
use App\Modules\Sales\Presentation\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Customer resource — only the 7 RESTful methods.
 */
final class CustomerController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CustomerModel::query()->where('company_id', $request->user()->company_id);

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q
                ->where('registered_name', 'ilike', $term)
                ->orWhere('customer_no', 'ilike', $term)
                ->orWhere('tin', 'ilike', $term));
        }
        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return CustomerResource::collection(
            $query->orderBy('registered_name')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $customer): CustomerResource
    {
        return new CustomerResource(
            CustomerModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($customer)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse(['payment_terms_options' => [0, 7, 15, 30, 45, 60, 90]]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $action): JsonResponse
    {
        $customer = $action->execute(
            companyId: $request->user()->company_id,
            data:      $request->validated(),
            actorId:   $request->user()->id,
        );

        $model = CustomerModel::findOrFail($customer->id->value);

        return new JsonResponse(new CustomerResource($model), 201);
    }

    public function edit(Request $request, string $customer): CustomerResource
    {
        return $this->show($request, $customer);
    }

    public function update(Request $request, string $customer): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdateCustomer action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $customer): JsonResponse
    {
        // Soft-disable; never hard-delete (BIR audit trail)
        CustomerModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $customer)
            ->update(['is_active' => false]);

        return new JsonResponse(null, 204);
    }
}
