<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Contracts;

use App\Modules\Projects\Domain\Entities\WipEntry;

interface WipRepositoryContract
{
    public function save(WipEntry $entry): void;

    /** @return list<WipEntry> */
    public function listForProject(string $projectId): array;
}
