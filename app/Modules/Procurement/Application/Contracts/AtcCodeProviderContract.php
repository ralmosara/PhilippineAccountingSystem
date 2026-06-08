<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Contracts;

/**
 * Reads ATC code rates from `tax.atc_codes`. Owned by the Tax module
 * conceptually; Procurement consumes via this contract so it doesn't
 * import the Tax module's Eloquent model directly.
 */
interface AtcCodeProviderContract
{
    /**
     * @return array{code: string, rate: string, kind: string}|null
     */
    public function find(string $code): ?array;
}
