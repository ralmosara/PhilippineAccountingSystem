<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Entities;

use DateTimeImmutable;

final readonly class AlphalistEntry
{
    public function __construct(
        public string $schedule,                  // 'sawt' | 'qap' | 'map' | '7_1' | ...
        public string $tin,
        public string $registeredName,
        public ?string $atcCode,
        public string $incomePayment,             // numeric string
        public string $taxWithheld,
        public ?string $taxType = null,           // 'I' | 'F' | 'C'
        public ?DateTimeImmutable $paymentDate = null,
        public ?string $natureOfPayment = null,
    ) {
    }
}
