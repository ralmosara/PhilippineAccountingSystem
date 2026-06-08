<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Exceptions;

use DomainException;

final class SameRegimeSupersedeException extends DomainException
{
}
