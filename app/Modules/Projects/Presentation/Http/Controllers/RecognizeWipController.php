<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Controllers;

use App\Modules\Projects\Application\Actions\RecognizeWip;
use App\Modules\Projects\Application\Exceptions\ProjectNotFoundException;
use App\Modules\Projects\Infrastructure\Persistence\Eloquent\WipEntryModel;
use App\Modules\Projects\Presentation\Http\Requests\RecognizeWipRequest;
use App\Modules\Projects\Presentation\Http\Resources\WipEntryResource;
use Illuminate\Http\JsonResponse;

/** POST /api/v1/projects/{project}/recognize-wip  (MFA required) */
final class RecognizeWipController
{
    public function __invoke(
        RecognizeWipRequest $request,
        RecognizeWip $action,
        string $project,
    ): JsonResponse {
        try {
            $wipEntry = $action->execute(
                projectId: $project,
                companyId: (string) $request->user()->company_id,
                data:      $request->validated(),
                actorId:   (string) $request->user()->id,
            );
        } catch (ProjectNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        }

        $model = WipEntryModel::query()->findOrFail($wipEntry->id);
        return new JsonResponse(new WipEntryResource($model), 201);
    }
}
