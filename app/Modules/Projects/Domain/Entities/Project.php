<?php

declare(strict_types=1);

namespace App\Modules\Projects\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Projects\Domain\ValueObjects\ProjectId;
use DateTimeImmutable;
use DomainException;

final class Project
{
    public string $status = 'draft';

    public ?DateTimeImmutable $completedAt = null;

    public function __construct(
        public readonly ProjectId $id,
        public readonly string $companyId,
        public string $code,
        public string $name,
        public string $billingType,              // fixed_price | time_and_materials | retainer
        public ?Money $contractValue = null,
        public ?string $budgetHours = null,
        public ?string $customerId = null,
        public ?string $wipAccountId = null,
        public ?string $revenueAccountId = null,
        public ?DateTimeImmutable $startsOn = null,
        public ?DateTimeImmutable $endsOn = null,
    ) {
    }

    public function activate(): void
    {
        if (! in_array($this->status, ['draft', 'on_hold'], true)) {
            throw new DomainException("Cannot activate a project in status: {$this->status}");
        }
        $this->status = 'active';
    }

    public function complete(): void
    {
        if ($this->status !== 'active') {
            throw new DomainException("Cannot complete a project in status: {$this->status}");
        }
        $this->status      = 'completed';
        $this->completedAt = new DateTimeImmutable();
    }

    public function hold(): void
    {
        if ($this->status !== 'active') {
            throw new DomainException("Cannot put on hold a project in status: {$this->status}");
        }
        $this->status = 'on_hold';
    }

    public function cancel(): void
    {
        if ($this->status === 'completed') {
            throw new DomainException('Cannot cancel a completed project.');
        }
        $this->status = 'cancelled';
    }
}
