<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderId;

final readonly class ProductionRunLine
{
    public function __construct(
        public string       $id,
        public WorkOrderId  $workOrderId,
        public string       $componentItemId,
        public string       $componentName,
        public string       $quantityRequired,  // BCMath string
        public string       $quantityConsumed,  // BCMath string
        public Money        $unitCost,
        public Money        $totalCost,
    ) {
    }
}
