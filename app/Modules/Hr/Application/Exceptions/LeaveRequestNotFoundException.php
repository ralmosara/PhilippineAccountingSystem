<?php

declare(strict_types=1);

namespace App\Modules\Hr\Application\Exceptions;

use RuntimeException;

final class LeaveRequestNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Leave request {$id} not found.");
    }
}
