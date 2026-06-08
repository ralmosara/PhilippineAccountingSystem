<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;

final readonly class PayslipLine
{
    public function __construct(
        public int $lineNo,
        public string $lineType,                  // earning | allowance | deduction | tax | statutory | loan
        public string $code,
        public string $description,
        public Money $amount,                     // negative for deductions
        public ?string $quantity = null,
        public ?string $rate = null,
    ) {
    }
}
