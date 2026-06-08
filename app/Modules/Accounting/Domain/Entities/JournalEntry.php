<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\JournalEntryId;
use App\Modules\Accounting\Domain\ValueObjects\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class JournalEntry
{
    /** @var array<int, JournalLine> */
    public array $lines = [];

    public ?DateTimeImmutable $postedAt = null;

    public ?string $postedBy = null;

    public function __construct(
        public readonly JournalEntryId $id,
        public readonly string $companyId,
        public readonly string $fiscalPeriodId,
        public readonly string $documentSeriesId,
        public readonly string $docNo,             // 'JV-2026-000001'
        public readonly DateTimeImmutable $entryDate,
        public readonly string $source,            // manual|sales|purchase|...
        public readonly ?string $sourceDocId = null,
        public readonly ?string $sourceDocType = null,
        public readonly ?string $memo = null,
    ) {
    }

    public function addLine(JournalLine $line): void
    {
        if ($this->postedAt !== null) {
            throw new DomainException('Cannot add lines to a posted journal entry.');
        }
        $this->lines[] = $line;
    }

    public function totalDebits(): Money
    {
        $sum = Money::zero();
        foreach ($this->lines as $line) {
            $sum = $sum->add($line->debit);
        }
        return $sum;
    }

    public function totalCredits(): Money
    {
        $sum = Money::zero();
        foreach ($this->lines as $line) {
            $sum = $sum->add($line->credit);
        }
        return $sum;
    }

    public function isBalanced(): bool
    {
        return $this->totalDebits()->equals($this->totalCredits());
    }

    public function post(string $postedBy): void
    {
        if ($this->postedAt !== null) {
            throw new DomainException("Journal entry {$this->docNo} is already posted.");
        }
        if (count($this->lines) < 2) {
            throw new DomainException('A journal entry must have at least 2 lines.');
        }
        if (! $this->isBalanced()) {
            throw new DomainException(sprintf(
                'Journal entry %s is not balanced: debits=%s credits=%s',
                $this->docNo,
                $this->totalDebits()->toPhp(),
                $this->totalCredits()->toPhp(),
            ));
        }

        $this->postedAt = new DateTimeImmutable();
        $this->postedBy = $postedBy;
    }
}
