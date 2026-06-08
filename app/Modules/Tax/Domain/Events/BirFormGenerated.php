<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Events;

use DateTimeImmutable;

final readonly class BirFormGenerated
{
    public function __construct(
        public string $birFormId,
        public string $companyId,
        public string $formType,
        public DateTimeImmutable $periodFrom,
        public DateTimeImmutable $periodTo,
        public string $taxDue,
        public string $generatedBy,
    ) {
    }
}
