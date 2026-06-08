<?php

declare(strict_types=1);

namespace App\Modules\Projects\Presentation\Http\Controllers;

use App\Modules\Projects\Application\Actions\LogTimesheetEntry;
use App\Modules\Projects\Application\Exceptions\ProjectNotFoundException;
use App\Modules\Projects\Infrastructure\Persistence\Eloquent\TimesheetEntryModel;
use App\Modules\Projects\Presentation\Http\Requests\StoreTimesheetEntryRequest;
use App\Modules\Projects\Presentation\Http\Resources\TimesheetEntryResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Timesheet entries sub-resource — index and store only. */
final class TimesheetEntryController
{
    public function index(Request $request, string $project): AnonymousResourceCollection
    {
        return TimesheetEntryResource::collection(
            TimesheetEntryModel::query()
                ->where('project_id', $project)
                ->orderByDesc('work_date')
                ->paginate($request->integer('per_page', 50))
        );
    }

    public function store(
        StoreTimesheetEntryRequest $request,
        LogTimesheetEntry $action,
        string $project,
    ): JsonResponse {
        try {
            $entry = $action->execute(
                projectId: $project,
                companyId: (string) $request->user()->company_id,
                data:      $request->validated(),
                actorId:   (string) $request->user()->id,
            );
        } catch (ProjectNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        $model = TimesheetEntryModel::query()->findOrFail($entry->id);
        return new JsonResponse(new TimesheetEntryResource($model), 201);
    }
}
