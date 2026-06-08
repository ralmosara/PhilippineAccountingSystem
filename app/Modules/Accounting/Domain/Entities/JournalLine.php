<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\AccountId;
use App\Modules\Accounting\Domain\ValueObjects\Money;
use InvalidArgumentException;

final readonly class JournalLine
{
    public function __construct(
        public int $lineNo,
        public AccountId $accountId,
        public Money $debit,
        public Money $credit,
        public Money $phpAmount,         // signed: debit positive, credit negative (or vice versa per convention)
        public string $fxRate = '1',
        public ?string $taxCodeId = null,
        public ?string $costCenterId = null,
        public ?string $projectId = null,
        public ?string $memo = null,
    ) {
        if ($debit->isPositive() && $credit->isPositive()) {
            throw new InvalidArgumentException('Line cannot have both debit and credit > 0.');
        }
        if ($debit->isNegative() || $credit->isNegative()) {
            throw new InvalidArgumentException('Debit and credit must be non-negative.');
        }
    }

    public function isDebit(): bool
    {
        return $this->debit->isPositive();
    }

    public function isCredit(): bool
    {
        return $this->credit->isPositive();
    }

    /** Magnitude (always positive) regardless of side. */
    public function amount(): Money
    {
        return $this->isDebit() ? $this->debit : $this->credit;
    }
}
