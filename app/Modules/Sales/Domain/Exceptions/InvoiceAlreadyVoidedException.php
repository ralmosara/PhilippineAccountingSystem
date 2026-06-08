<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Exceptions;

use DomainException;

final class InvoiceAlreadyVoidedException extends DomainException
{
    public function __construct(string $docNo)
    {
        parent::__construct("Invoice {$docNo} is already voided.");
    }
}
