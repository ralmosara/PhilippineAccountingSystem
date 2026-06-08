<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Exceptions;

use RuntimeException;

final class DuplicateProjectCodeException extends RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct("A project with code '{$code}' already exists for this company.");
    }
}
