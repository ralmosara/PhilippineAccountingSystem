<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Exceptions;

use DomainException;

final class ThreeWayMatchVarianceException extends DomainException
{
    /**
     * @param  list<array<string, mixed>>  $variances
     */
    public function __construct(public readonly array $variances)
    {
        parent::__construct(sprintf(
            'Three-way match variance detected on %d line(s); approver override required.',
            count($variances),
        ));
    }
}
