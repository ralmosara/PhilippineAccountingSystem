<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Events;

use DateTimeImmutable;

final readonly class BirFormFiled
{
    public function __construct(
        public string $birFormId,
        public string $companyId,
        public string $formType,
        public string $birFilingRef,
        public string $channel,                   // 'ebirforms_offline' | 'efps' | ...
        public DateTimeImmutable $filedAt,
        public string $filedBy,
    ) {
    }
}
