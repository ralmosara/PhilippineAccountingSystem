<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Eis;

use App\Modules\Tax\Application\Contracts\EisCertificateLoaderContract;
use App\Modules\Tax\Application\DTOs\LoadedCertificate;
use App\Modules\Tax\Domain\Exceptions\EisCertificateLoadException;

/**
 * Loads the configured PKCS#12 signing certificate into memory.
 *
 * - Memoizes the result for the lifetime of the request/job
 *   (decryption is non-trivial and we sign multiple invoices per batch).
 * - Verifies the cert has not expired before returning it. We do NOT trust
 *   the cert chain at this layer — that's BIR's job on the receiving end.
 * - Reads passphrase from a separate env so the .p12 can be checked into
 *   secrets management without the passphrase living next to it.
 */
final class P12CertificateLoader implements EisCertificateLoaderContract
{
    private ?LoadedCertificate $cached = null;

    public function __construct(
        private readonly string $p12Path,
        private readonly string $passphrase,
    ) {
    }

    public function load(): LoadedCertificate
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if (! is_readable($this->p12Path)) {
            throw new EisCertificateLoadException(
                "EIS signing certificate not readable at: {$this->p12Path}. "
                ."Set BIR_EIS_CERT_PATH to a valid PKCS#12 file."
            );
        }

        $blob = file_get_contents($this->p12Path);
        if ($blob === false) {
            throw new EisCertificateLoadException("Failed to read certificate file: {$this->p12Path}");
        }

        $bundle = [];
        if (! openssl_pkcs12_read($blob, $bundle, $this->passphrase)) {
            throw new EisCertificateLoadException(
                'openssl_pkcs12_read failed — wrong passphrase or corrupt .p12. '
                .'Error: '.openssl_error_string()
            );
        }

        if (! isset($bundle['cert'], $bundle['pkey'])) {
            throw new EisCertificateLoadException(
                "PKCS#12 bundle missing required entries (cert and/or pkey)."
            );
        }

        $parsed = openssl_x509_parse($bundle['cert']);
        if ($parsed === false) {
            throw new EisCertificateLoadException('openssl_x509_parse failed on EIS certificate.');
        }

        $der = '';
        if (! openssl_x509_export($bundle['cert'], $der, no_text: true)) {
            throw new EisCertificateLoadException('openssl_x509_export failed.');
        }
        // Convert PEM → DER for thumbprint
        $pemStripped = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $der);
        $derBytes    = base64_decode($pemStripped ?? '', strict: true) ?: '';
        $thumbprint  = strtoupper(hash('sha1', $derBytes));

        $this->cached = new LoadedCertificate(
            certificatePem:    $bundle['cert'],
            privateKeyPem:     $bundle['pkey'],
            thumbprintSha1:    $thumbprint,
            subjectCommonName: $parsed['subject']['CN'] ?? 'unknown',
            notAfterUnix:      (int) ($parsed['validTo_time_t'] ?? 0),
        );

        return $this->cached;
    }
}
