<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Contracts;

use App\Modules\Projects\Domain\Entities\Project;
use App\Modules\Projects\Domain\ValueObjects\ProjectId;

interface ProjectRepositoryContract
{
    public function findById(ProjectId $id): ?Project;

    public function findByCode(string $companyId, string $code): ?Project;

    public function save(Project $project): void;

    /** @return list<Project> */
    public function listForCompany(string $companyId): array;
}
