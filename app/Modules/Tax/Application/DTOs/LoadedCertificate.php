<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\DTOs;

/**
 * An X.509 certificate + private key pair, unwrapped from a PKCS#12 (.p12)
 * container in memory. Held briefly to sign one batch; never persisted.
 *
 * `certificatePem` and `privateKeyPem` are PHP openssl resource strings; the
 * loader is responsible for zeroing them when the request lifecycle ends
 * (the cert loader keeps them inside a short-lived singleton).
 */
final readonly class LoadedCertificate
{
    public function __construct(
        /** PEM-encoded X.509 certificate. */
        public string $certificatePem,

        /** PEM-encoded RSA/ECDSA private key. */
        public string $privateKeyPem,

        /** SHA-1 fingerprint of the certificate (BIR identifier). */
        public string $thumbprintSha1,

        /** Subject CN — for logging only. */
        public string $subjectCommonName,

        /** Unix timestamp; signing must refuse to use an expired cert. */
        public int $notAfterUnix,
    ) {
    }

    public function isExpired(?int $atUnix = null): bool
    {
        return $this->notAfterUnix <= ($atUnix ?? time());
    }
}
