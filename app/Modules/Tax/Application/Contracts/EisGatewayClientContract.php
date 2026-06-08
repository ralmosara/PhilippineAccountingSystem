<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Application\DTOs\EisTransmissionResult;
use App\Modules\Tax\Application\DTOs\SignedEisPayload;

interface EisGatewayClientContract
{
    /**
     * POSTs a signed payload to the BIR EIS issuance endpoint.
     *
     * Returns an EisTransmissionResult — never throws on HTTP-level errors.
     * (The caller decides retry vs fail-permanently based on `outcome`.)
     *
     * Only throws on programmer errors (malformed config, etc.).
     */
    public function transmit(SignedEisPayload $signed): EisTransmissionResult;
}
