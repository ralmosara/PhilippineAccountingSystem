<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Events;

use DateTimeImmutable;

final readonly class Form2307Issued
{
    public function __construct(
        public string $form2307Id,
        public string $companyId,
        public string $vendorId,
        public string $vendorBillId,
        public string $atcCode,
        public string $taxWithheld,
        public DateTimeImmutable $periodFrom,
        public DateTimeImmutable $periodTo,
        public string $issuedBy,
    ) {
    }
}
