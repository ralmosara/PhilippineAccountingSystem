<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Contracts;

use App\Modules\Accounting\Domain\Entities\Account;
use App\Modules\Accounting\Domain\ValueObjects\AccountCode;
use App\Modules\Accounting\Domain\ValueObjects\AccountId;

interface AccountRepositoryContract
{
    public function findById(AccountId $id): ?Account;

    public function findByCode(string $companyId, AccountCode $code): ?Account;

    /** Returns true iff the account exists, is active, and is_postable. */
    public function isPostable(AccountId $id): bool;
}
