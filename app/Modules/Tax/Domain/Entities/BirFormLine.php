<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Entities;

final readonly class BirFormLine
{
    /**
     * @param  array<string, mixed>  $breakdown  rows that aggregate into this line
     */
    public function __construct(
        public string $lineCode,                  // '1A', '2', '4B' — matches BIR form
        public string $description,
        public string $amount,                    // numeric string (BCMath)
        public array $breakdown = [],
    ) {
    }
}
