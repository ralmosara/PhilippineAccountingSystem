<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Exceptions;

use RuntimeException;

final class AccountNotPostableException extends RuntimeException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Account {$accountId} is not postable (header/group account or inactive).");
    }
}
