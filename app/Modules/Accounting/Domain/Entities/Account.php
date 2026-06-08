<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\AccountCode;
use App\Modules\Accounting\Domain\ValueObjects\AccountId;

final readonly class Account
{
    public const TYPE_ASSET             = 'asset';
    public const TYPE_LIABILITY         = 'liability';
    public const TYPE_EQUITY            = 'equity';
    public const TYPE_REVENUE           = 'revenue';
    public const TYPE_EXPENSE           = 'expense';
    public const TYPE_CONTRA_ASSET      = 'contra_asset';
    public const TYPE_CONTRA_LIABILITY  = 'contra_liability';
    public const TYPE_CONTRA_EQUITY     = 'contra_equity';

    public function __construct(
        public AccountId $id,
        public string $companyId,
        public AccountCode $code,
        public string $name,
        public string $type,
        public string $normalBalance,    // 'debit' | 'credit'
        public ?AccountId $parentId,
        public string $path,             // ltree path string
        public bool $isPostable,
        public bool $isActive,
    ) {
    }

    /** Whether a given debit/credit pair increases or decreases this account. */
    public function increasesOnDebit(): bool
    {
        return $this->normalBalance === 'debit';
    }
}
