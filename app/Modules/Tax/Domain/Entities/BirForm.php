<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Entities;

use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use DateTimeImmutable;

final class BirForm
{
    /** @var array<int, BirFormLine> */
    public array $lines = [];

    /** @var array<int, AlphalistEntry> */
    public array $alphalistEntries = [];

    public ?DateTimeImmutable $generatedAt = null;

    public ?DateTimeImmutable $filedAt = null;

    public ?string $birFilingRef = null;

    public string $status = 'draft';

    public string $taxDue = '0.00';

    public string $taxPaid = '0.00';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $xmlPath = null;

    public ?string $pdfPath = null;

    public ?string $datPath = null;

    public function __construct(
        public readonly BirFormId $id,
        public readonly string $companyId,
        public readonly string $formType,             // '2550M', '1601EQ', etc.
        public readonly FormPeriod $period,
    ) {
    }

    public function addLine(BirFormLine $line): void
    {
        $this->lines[] = $line;
    }

    public function addAlphalistEntry(AlphalistEntry $entry): void
    {
        $this->alphalistEntries[] = $entry;
    }

    public function markGenerated(string $taxDue, string $taxPaid = '0.00'): void
    {
        $this->generatedAt = new DateTimeImmutable();
        $this->status      = 'generated';
        $this->taxDue      = $taxDue;
        $this->taxPaid     = $taxPaid;
    }

    public function markFiled(string $birFilingRef): void
    {
        $this->filedAt      = new DateTimeImmutable();
        $this->status       = 'filed';
        $this->birFilingRef = $birFilingRef;
    }
}
