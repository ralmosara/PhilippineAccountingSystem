<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class NegativeStockException extends DomainException
{
    public function __construct(
        public readonly string $itemId,
        public readonly string $warehouseId,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for item %s in warehouse %s: have %s, need %s.',
            $itemId, $warehouseId, $available, $requested,
        ));
    }
}
