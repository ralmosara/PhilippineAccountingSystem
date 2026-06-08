<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Exceptions;

use RuntimeException;

final class CustomerNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Customer {$id} not found.");
    }
}
