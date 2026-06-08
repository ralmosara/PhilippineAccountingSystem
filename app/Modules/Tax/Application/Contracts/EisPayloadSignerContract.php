<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Application\DTOs\SignedEisPayload;

interface EisPayloadSignerContract
{
    /**
     * Canonicalize, sign, and return a SignedEisPayload ready to transmit.
     *
     * @param  array<string, mixed>  $payload  the EisPayloadBuilder output
     *
     * @throws \App\Modules\Tax\Domain\Exceptions\EisCertificateExpiredException
     * @throws \App\Modules\Tax\Domain\Exceptions\EisSigningFailedException
     */
    public function sign(array $payload): SignedEisPayload;
}
