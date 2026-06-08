<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Exceptions;

use RuntimeException;

final class VendorNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Vendor {$id} not found.");
    }
}
