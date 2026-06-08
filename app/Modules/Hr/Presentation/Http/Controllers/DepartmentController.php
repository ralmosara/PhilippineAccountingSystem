<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Controllers;

use App\Modules\Hr\Infrastructure\Persistence\Eloquent\DepartmentModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Department resource — read/write (7 RESTful methods). */
final class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $departments = DepartmentModel::query()
            ->where('company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return new JsonResponse(['data' => $departments]);
    }

    public function show(Request $request, string $department): JsonResponse
    {
        $dept = DepartmentModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($department);

        return new JsonResponse(['data' => $dept->only(['id', 'code', 'name', 'is_active'])]);
    }

    public function create(): JsonResponse
    {
        return new JsonResponse(['message' => 'Department creation is managed via HR admin.'], 501);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('hr.employees.manage');

        $data = $request->validate([
            'code'      => ['required', 'string', 'max:20'],
            'name'      => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'uuid'],
        ]);

        $dept = DepartmentModel::create(array_merge($data, [
            'company_id' => $request->user()->company_id,
            'is_active'  => true,
        ]));

        return new JsonResponse(['data' => $dept->only(['id', 'code', 'name', 'is_active'])], 201);
    }

    public function edit(Request $request, string $department): JsonResponse
    {
        return $this->show($request, $department);
    }

    public function update(Request $request, string $department): JsonResponse
    {
        return new JsonResponse(['message' => 'Department updates are managed via HR admin.'], 501);
    }

    public function destroy(Request $request, string $department): JsonResponse
    {
        $this->authorize('hr.employees.manage');

        $dept = DepartmentModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($department);

        $dept->update(['is_active' => false]);

        return new JsonResponse(['message' => 'Department deactivated.']);
    }
}
