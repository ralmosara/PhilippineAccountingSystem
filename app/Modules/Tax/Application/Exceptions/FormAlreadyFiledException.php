<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Exceptions;

use RuntimeException;

final class FormAlreadyFiledException extends RuntimeException
{
    public function __construct(string $formType, string $period)
    {
        parent::__construct("BIR Form {$formType} for {$period} is already filed; create an amendment instead.");
    }
}
