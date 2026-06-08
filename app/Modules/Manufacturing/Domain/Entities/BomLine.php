<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Domain\Entities;

use App\Modules\Manufacturing\Domain\ValueObjects\BomId;

final readonly class BomLine
{
    public function __construct(
        public string $id,
        public BomId  $bomId,
        public string $componentItemId,
        public string $componentName,
        public string $quantityPerBatch,   // BCMath numeric string
        public string $unitOfMeasure,
        public ?string $notes,
    ) {
    }
}
