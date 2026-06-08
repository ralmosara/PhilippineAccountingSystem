<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Controllers;

use App\Modules\Projects\Application\Actions\CreateProject;
use App\Modules\Projects\Application\Exceptions\DuplicateProjectCodeException;
use App\Modules\Projects\Application\Exceptions\ProjectNotFoundException;
use App\Modules\Projects\Infrastructure\Persistence\Eloquent\ProjectModel;
use App\Modules\Projects\Presentation\Http\Requests\StoreProjectRequest;
use App\Modules\Projects\Presentation\Http\Requests\UpdateProjectRequest;
use App\Modules\Projects\Presentation\Http\Resources\ProjectResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Projects resource controller — list, show, create, update, soft-cancel. */
final class ProjectController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProjectModel::query()->with('timesheetEntries');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $companyId = $request->user()?->company_id;
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return ProjectResource::collection(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $project): ProjectResource
    {
        return new ProjectResource(
            ProjectModel::query()->with('timesheetEntries', 'wipEntries')->findOrFail($project)
        );
    }

    public function store(StoreProjectRequest $request, CreateProject $action): JsonResponse
    {
        try {
            $project = $action->execute(
                companyId: (string) $request->user()->company_id,
                data:      $request->validated(),
                actorId:   (string) $request->user()->id,
            );
        } catch (DuplicateProjectCodeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }

        $model = ProjectModel::query()->findOrFail($project->id->value);
        return new JsonResponse(new ProjectResource($model), 201);
    }

    public function update(UpdateProjectRequest $request, string $project): JsonResponse
    {
        $model = ProjectModel::query()->findOrFail($project);

        try {
            $this->applyUpdates($model, $request->validated());
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        $model->save();

        return new JsonResponse(new ProjectResource($model->fresh('timesheetEntries')));
    }

    public function destroy(Request $request, string $project): JsonResponse
    {
        $model = ProjectModel::query()->findOrFail($project);

        if (! in_array($model->status, ['draft', 'active', 'on_hold'], true)) {
            return new JsonResponse(['message' => 'Only draft, active, or on-hold projects can be cancelled.'], 422);
        }

        $model->status = 'cancelled';
        $model->save();

        return new JsonResponse(null, 204);
    }

    /** @param  array<string, mixed>  $data */
    private function applyUpdates(ProjectModel $model, array $data): void
    {
        foreach (['name', 'billing_type', 'contract_value', 'budget_hours',
                  'customer_id', 'wip_account_id', 'revenue_account_id',
                  'starts_on', 'ends_on'] as $field) {
            if (array_key_exists($field, $data)) {
                $model->{$field} = $data[$field];
            }
        }

        if (isset($data['status'])) {
            match ($data['status']) {
                'active'    => $model->status = 'active',
                'on_hold'   => $model->status = 'on_hold',
                'cancelled' => $model->status = 'cancelled',
                default     => null,
            };
        }
    }
}
