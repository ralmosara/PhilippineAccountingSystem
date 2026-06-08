<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Application\DTOs\LoadedCertificate;

interface EisCertificateLoaderContract
{
    /**
     * Decrypts and parses the configured PKCS#12 (.p12) signing certificate.
     * The result is cached for the request lifecycle so we don't decrypt
     * the same P12 dozens of times during a batch transmission.
     *
     * @throws \App\Modules\Tax\Domain\Exceptions\EisCertificateLoadException
     */
    public function load(): LoadedCertificate;
}
