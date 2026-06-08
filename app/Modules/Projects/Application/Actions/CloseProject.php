<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Projects\Application\Contracts\ProjectRepositoryContract;
use App\Modules\Projects\Application\Exceptions\ProjectNotFoundException;
use App\Modules\Projects\Domain\Entities\Project;
use App\Modules\Projects\Domain\Events\ProjectCompleted;
use App\Modules\Projects\Domain\ValueObjects\ProjectId;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

final readonly class CloseProject
{
    public function __construct(
        private ProjectRepositoryContract $projects,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $projectId, string $companyId, string $actorId): Project
    {
        return DB::transaction(function () use ($projectId, $companyId, $actorId) {
            $project = $this->projects->findById(new ProjectId($projectId))
                ?? throw new ProjectNotFoundException($projectId);

            $project->complete();
            $this->projects->save($project);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'project.completed',
                aggregate:   'Project',
                aggregateId: $project->id->value,
                payload: [
                    'code'         => $project->code,
                    'name'         => $project->name,
                    'completed_at' => $project->completedAt?->format('Y-m-d\TH:i:sP'),
                ],
            );

            $this->events->dispatch(new ProjectCompleted(
                projectId:   $project->id->value,
                companyId:   $companyId,
                code:        $project->code,
                name:        $project->name,
                completedAt: $project->completedAt,
                completedBy: $actorId,
            ));

            return $project;
        });
    }
}
