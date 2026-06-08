<?php

declare(strict_types=1);

namespace App\Modules\Projects\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use DateTimeImmutable;
use DomainException;

final class WipEntry
{
    public string $status = 'draft';

    public ?DateTimeImmutable $postedAt = null;

    public ?string $postedBy = null;

    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        public readonly DateTimeImmutable $periodFrom,
        public readonly DateTimeImmutable $periodTo,
        public string $totalHours,
        public Money $totalCost,
        public Money $totalBilled,
        public Money $recognizedRevenue,
        public ?string $journalEntryId = null,
    ) {
    }

    public function post(string $actorId): void
    {
        if ($this->status === 'posted') {
            throw new DomainException('WIP entry is already posted.');
        }
        $this->status   = 'posted';
        $this->postedAt = new DateTimeImmutable();
        $this->postedBy = $actorId;
    }
}
