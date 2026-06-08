<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\CompensationPackageModel;
use App\Modules\Payroll\Presentation\Http\Requests\StoreCompensationPackageRequest;
use App\Modules\Payroll\Presentation\Http\Resources\CompensationPackageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class CompensationPackageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $employeeId = $request->query('employee_id');

        $query = CompensationPackageModel::query()->orderByDesc('effective_from');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return CompensationPackageResource::collection($query->get());
    }

    public function store(StoreCompensationPackageRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Derive basic_daily from basic_monthly / working_days if not provided
        if (empty($data['basic_daily']) && !empty($data['basic_monthly']) && !empty($data['working_days_per_month'])) {
            $data['basic_daily'] = bcdiv((string) $data['basic_monthly'], (string) $data['working_days_per_month'], 2);
        }

        // Derive hourly_rate from basic_daily / hours_per_day if not provided
        if (empty($data['hourly_rate']) && !empty($data['basic_daily']) && !empty($data['hours_per_day'])) {
            $data['hourly_rate'] = bcdiv((string) $data['basic_daily'], (string) $data['hours_per_day'], 4);
        }

        $pkg = CompensationPackageModel::create($data);

        return (new CompensationPackageResource($pkg))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CompensationPackageModel $compensationPackage): CompensationPackageResource
    {
        return new CompensationPackageResource($compensationPackage);
    }

    public function update(StoreCompensationPackageRequest $request, CompensationPackageModel $compensationPackage): CompensationPackageResource
    {
        $data = $request->validated();
        $compensationPackage->update($data);

        return new CompensationPackageResource($compensationPackage->fresh());
    }

    public function destroy(CompensationPackageModel $compensationPackage): JsonResponse
    {
        // Close out the package by setting effective_to to today rather than hard-deleting
        $compensationPackage->update(['effective_to' => now()->toDateString()]);

        return response()->json(['message' => 'Compensation package closed out.']);
    }
}
