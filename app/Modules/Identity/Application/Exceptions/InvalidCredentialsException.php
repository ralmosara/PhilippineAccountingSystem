<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Exceptions;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The provided credentials are incorrect.');
    }
}
