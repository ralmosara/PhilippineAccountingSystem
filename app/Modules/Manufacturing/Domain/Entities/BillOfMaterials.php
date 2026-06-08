<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Domain\Entities;

use App\Modules\Manufacturing\Domain\ValueObjects\BomId;
use DomainException;

final class BillOfMaterials
{
    /** @var list<BomLine> */
    public array $lines = [];

    public function __construct(
        public readonly BomId $id,
        public readonly string $companyId,
        public readonly string $itemId,
        public readonly string $itemName,
        public readonly string $code,
        public readonly string $name,
        public string $version,
        public string $status,              // draft|active|superseded
        public string $standardBatchSize,  // BCMath string
        public string $laborCostPerBatch,  // BCMath string
        public string $overheadCostPerBatch, // BCMath string
        public ?string $notes,
    ) {
    }

    public function addLine(BomLine $line): void
    {
        if ($this->status === 'superseded') {
            throw new DomainException('Cannot add lines to a superseded BOM.');
        }
        $this->lines[] = $line;
    }

    public function activate(): void
    {
        if ($this->status !== 'draft') {
            throw new DomainException("BOM {$this->code} can only be activated from draft status; current status: {$this->status}.");
        }
        if (count($this->lines) === 0) {
            throw new DomainException("BOM {$this->code} must have at least one component line before activation.");
        }
        $this->status = 'active';
    }

    public function supersede(): void
    {
        if ($this->status === 'superseded') {
            throw new DomainException("BOM {$this->code} is already superseded.");
        }
        $this->status = 'superseded';
    }
}
