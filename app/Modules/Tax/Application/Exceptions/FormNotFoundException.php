<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Exceptions;

use RuntimeException;

final class FormNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("BIR form {$id} not found.");
    }
}
