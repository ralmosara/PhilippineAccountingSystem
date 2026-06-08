<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\DTOs;

/**
 * The canonical bytes of a payload + its PKCS#7 detached signature,
 * ready to POST to BIR EIS.
 */
final readonly class SignedEisPayload
{
    public function __construct(
        /** Canonical JSON bytes (RFC 8785) — exactly what was signed. */
        public string $canonicalBytes,

        /** PEM-armoured PKCS#7 detached signature. */
        public string $signaturePem,

        /** SHA-256 hex digest of $canonicalBytes — stored for audit cross-checks. */
        public string $payloadHash,

        /** SHA-1 thumbprint of the X.509 signing cert (BIR convention). */
        public string $certThumbprint,
    ) {
    }
}
