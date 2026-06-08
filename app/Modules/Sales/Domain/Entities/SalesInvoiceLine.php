<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;

final readonly class SalesInvoiceLine
{
    public function __construct(
        public int $lineNo,
        public string $description,
        public string $quantity,                 // numeric string for BCMath
        public Money $unitPrice,
        public Money $vatAmount,
        public Money $lineTotal,                 // net of discount, includes VAT
        public ?string $itemId = null,
        public ?string $taxCodeId = null,
        public ?string $revenueAccountId = null,
        public ?string $projectId = null,
        public string $discountPct = '0',
        public ?Money $discountAmount = null,
    ) {
    }
}
