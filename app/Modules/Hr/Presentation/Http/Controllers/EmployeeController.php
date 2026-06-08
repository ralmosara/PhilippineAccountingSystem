<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Controllers;

use App\Modules\Hr\Application\Actions\CreateEmployee;
use App\Modules\Hr\Infrastructure\Persistence\Eloquent\EmployeeModel;
use App\Modules\Hr\Presentation\Http\Requests\StoreEmployeeRequest;
use App\Modules\Hr\Presentation\Http\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Employee resource — 7 RESTful methods only. */
final class EmployeeController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = EmployeeModel::query()
            ->where('company_id', $request->user()->company_id);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true)->whereNull('separated_on');
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->string('department_id'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q
                ->where('first_name', 'ilike', $term)
                ->orWhere('last_name', 'ilike', $term)
                ->orWhere('employee_no', 'ilike', $term));
        }

        return EmployeeResource::collection(
            $query->orderBy('last_name')->orderBy('first_name')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $employee): EmployeeResource
    {
        return new EmployeeResource(
            EmployeeModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($employee)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'employment_statuses' => ['probationary', 'regular', 'contract', 'project', 'consultant'],
        ]);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $action): JsonResponse
    {
        $employee = $action->execute(
            companyId: $request->user()->company_id,
            data:      $request->validated(),
            actorId:   $request->user()->id,
        );

        $model = EmployeeModel::findOrFail($employee->id->value);
        return new JsonResponse(new EmployeeResource($model), 201);
    }

    public function edit(Request $request, string $employee): EmployeeResource
    {
        return $this->show($request, $employee);
    }

    public function update(Request $request, string $employee): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdateEmployee action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $employee): JsonResponse
    {
        // Never hard-delete — separated employees retain records for BIR audit trail
        EmployeeModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('id', $employee)
            ->update(['is_active' => false, 'separated_on' => now()]);

        return new JsonResponse(null, 204);
    }
}
