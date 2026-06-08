<?php

declare(strict_types=1);

namespace App\Modules\Projects\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Projects\Application\Contracts\ProjectRepositoryContract;
use App\Modules\Projects\Domain\Entities\Project;
use App\Modules\Projects\Domain\ValueObjects\ProjectId;
use App\Modules\Projects\Infrastructure\Persistence\Eloquent\ProjectModel;
use DateTimeImmutable;

final class EloquentProjectRepository implements ProjectRepositoryContract
{
    public function findById(ProjectId $id): ?Project
    {
        $model = ProjectModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $companyId, string $code): ?Project
    {
        $model = ProjectModel::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function save(Project $project): void
    {
        ProjectModel::query()->updateOrInsert(
            ['id' => $project->id->value],
            [
                'company_id'        => $project->companyId,
                'customer_id'       => $project->customerId,
                'code'              => $project->code,
                'name'              => $project->name,
                'billing_type'      => $project->billingType,
                'status'            => $project->status,
                'contract_value'    => $project->contractValue?->toPhp(),
                'budget_hours'      => $project->budgetHours,
                'wip_account_id'    => $project->wipAccountId,
                'revenue_account_id'=> $project->revenueAccountId,
                'starts_on'         => $project->startsOn?->format('Y-m-d'),
                'ends_on'           => $project->endsOn?->format('Y-m-d'),
                'completed_at'      => $project->completedAt?->format('Y-m-d\TH:i:sP'),
                'updated_at'        => now(),
                'created_at'        => now(),
            ],
        );
    }

    /** @return list<Project> */
    public function listForCompany(string $companyId): array
    {
        return ProjectModel::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ProjectModel $m) => $this->toDomain($m))
            ->values()
            ->all();
    }

    private function toDomain(ProjectModel $m): Project
    {
        $project = new Project(
            id:               new ProjectId($m->id),
            companyId:        $m->company_id,
            code:             $m->code,
            name:             $m->name,
            billingType:      $m->billing_type,
            contractValue:    $m->contract_value !== null ? Money::php((string) $m->contract_value) : null,
            budgetHours:      $m->budget_hours !== null ? (string) $m->budget_hours : null,
            customerId:       $m->customer_id,
            wipAccountId:     $m->wip_account_id,
            revenueAccountId: $m->revenue_account_id,
            startsOn:         $m->starts_on ? new DateTimeImmutable($m->starts_on->toDateString()) : null,
            endsOn:           $m->ends_on ? new DateTimeImmutable($m->ends_on->toDateString()) : null,
        );

        $project->status      = $m->status;
        $project->completedAt = $m->completed_at ? new DateTimeImmutable($m->completed_at->toIso8601String()) : null;

        return $project;
    }
}
