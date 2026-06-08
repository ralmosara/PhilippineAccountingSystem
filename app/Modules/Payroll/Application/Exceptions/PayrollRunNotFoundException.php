<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Exceptions;

use RuntimeException;

final class PayrollRunNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Payroll run {$id} not found.");
    }
}
