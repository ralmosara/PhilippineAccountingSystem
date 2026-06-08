<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Exceptions;

use RuntimeException;

final class ProjectNotFoundException extends RuntimeException
{
    public function __construct(string $projectId)
    {
        parent::__construct("Project not found: {$projectId}");
    }
}
