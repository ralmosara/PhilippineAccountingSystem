<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class ItemNotInventoriedException extends DomainException
{
    public function __construct(string $itemId)
    {
        parent::__construct("Item {$itemId} is not inventory-tracked (service or non-stock item).");
    }
}
