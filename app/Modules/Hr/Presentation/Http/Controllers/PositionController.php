<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Controllers;

use App\Modules\Hr\Infrastructure\Persistence\Eloquent\PositionModel;
use App\Modules\Hr\Presentation\Http\Resources\PositionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class PositionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PositionModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->orderBy('code');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        return PositionResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'             => ['required', 'string', 'max:20'],
            'title'            => ['required', 'string', 'max:150'],
            'department_id'    => ['nullable', 'uuid'],
            'salary_grade_min' => ['nullable', 'numeric', 'min:0'],
            'salary_grade_max' => ['nullable', 'numeric', 'min:0'],
        ]);

        $position = PositionModel::create(array_merge($data, [
            'company_id' => $request->user()->company_id,
            'is_active'  => true,
        ]));

        return (new PositionResource($position))->response()->setStatusCode(201);
    }

    public function show(PositionModel $position): PositionResource
    {
        return new PositionResource($position);
    }

    public function update(Request $request, PositionModel $position): PositionResource
    {
        $data = $request->validate([
            'code'             => ['sometimes', 'string', 'max:20'],
            'title'            => ['sometimes', 'string', 'max:150'],
            'department_id'    => ['nullable', 'uuid'],
            'salary_grade_min' => ['nullable', 'numeric', 'min:0'],
            'salary_grade_max' => ['nullable', 'numeric', 'min:0'],
            'is_active'        => ['boolean'],
        ]);

        $position->update($data);

        return new PositionResource($position->fresh());
    }

    public function destroy(PositionModel $position): JsonResponse
    {
        $position->update(['is_active' => false]);

        return response()->json(['message' => 'Position deactivated.']);
    }
}
