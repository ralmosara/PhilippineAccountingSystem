<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Exceptions;

use RuntimeException;

final class PayrollAlreadyApprovedException extends RuntimeException
{
    public function __construct(string $runNo)
    {
        parent::__construct("Payroll run {$runNo} is already approved; create an adjustment run instead.");
    }
}
