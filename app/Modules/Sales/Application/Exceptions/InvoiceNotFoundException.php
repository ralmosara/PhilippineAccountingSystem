<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Exceptions;

use RuntimeException;

final class InvoiceNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Sales invoice {$id} not found.");
    }
}
