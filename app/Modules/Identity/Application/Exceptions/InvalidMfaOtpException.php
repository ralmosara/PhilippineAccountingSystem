<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Exceptions;

use RuntimeException;

final class InvalidMfaOtpException extends RuntimeException
{
    public function __construct(string $message = 'The provided OTP is invalid.')
    {
        parent::__construct($message);
    }
}
