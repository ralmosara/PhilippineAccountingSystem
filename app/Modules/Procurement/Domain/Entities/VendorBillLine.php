<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;

final readonly class VendorBillLine
{
    public function __construct(
        public int $lineNo,
        public string $description,
        public string $quantity,
        public Money $unitPrice,
        public Money $vatAmount,
        public Money $lineTotal,
        public ?string $purchaseOrderLineId = null,
        public ?string $itemId = null,
        public ?string $expenseAccountId = null,
        public ?string $taxCodeId = null,
        public ?string $projectId = null,
    ) {
    }
}
