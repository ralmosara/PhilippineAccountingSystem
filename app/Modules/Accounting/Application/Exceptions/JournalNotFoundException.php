<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Exceptions;

use RuntimeException;

final class JournalNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Journal entry {$id} not found.");
    }
}
