<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Exceptions;

use RuntimeException;

final class ItemNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Item {$id} not found.");
    }
}
