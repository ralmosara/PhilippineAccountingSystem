<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Controllers;

use App\Modules\Projects\Application\Actions\CloseProject;
use App\Modules\Projects\Application\Exceptions\ProjectNotFoundException;
use App\Modules\Projects\Infrastructure\Persistence\Eloquent\ProjectModel;
use App\Modules\Projects\Presentation\Http\Resources\ProjectResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/v1/projects/{project}/close  (MFA required) */
final class CloseProjectController
{
    public function __invoke(
        Request $request,
        CloseProject $action,
        string $project,
    ): JsonResponse {
        $this->authorizeClose($request);

        try {
            $closed = $action->execute(
                projectId: $project,
                companyId: (string) $request->user()->company_id,
                actorId:   (string) $request->user()->id,
            );
        } catch (ProjectNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        $model = ProjectModel::query()->findOrFail($closed->id->value);
        return new JsonResponse(new ProjectResource($model));
    }

    private function authorizeClose(Request $request): void
    {
        if (! $request->user()?->can('projects.close')) {
            abort(403, 'Insufficient permissions to close a project.');
        }
    }
}
