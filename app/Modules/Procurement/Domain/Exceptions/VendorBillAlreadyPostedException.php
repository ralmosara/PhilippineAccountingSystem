<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Exceptions;

use DomainException;

final class VendorBillAlreadyPostedException extends DomainException
{
    public function __construct(string $vendorInvoiceNo)
    {
        parent::__construct("Vendor bill {$vendorInvoiceNo} is already posted.");
    }
}
