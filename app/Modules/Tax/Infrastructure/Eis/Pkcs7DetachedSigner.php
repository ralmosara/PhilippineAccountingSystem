<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Eis;

use App\Modules\Tax\Application\Contracts\EisCertificateLoaderContract;
use App\Modules\Tax\Application\Contracts\EisPayloadSignerContract;
use App\Modules\Tax\Application\DTOs\SignedEisPayload;
use App\Modules\Tax\Domain\Exceptions\EisCertificateExpiredException;
use App\Modules\Tax\Domain\Exceptions\EisSigningFailedException;
use App\Modules\Tax\Domain\Services\Eis\JsonCanonicalizer;

/**
 * Signs an EIS payload using PKCS#7/CMS detached signature, the format
 * mandated by RR 8-2022 § 4.b. Equivalent to the .NET reference
 * implementation BIR publishes for EIS sandbox onboarding.
 *
 * "Detached" means the signed bytes (the canonical JSON) are transmitted
 * separately from the signature blob — BIR re-canonicalizes the payload
 * on its side and recomputes the digest.
 *
 * We write canonical bytes to a temp file because openssl_pkcs7_sign()
 * only accepts filenames (PHP openssl ext limitation). Temp files live
 * in sys_get_temp_dir() and are unlinked unconditionally in finally{}.
 */
final readonly class Pkcs7DetachedSigner implements EisPayloadSignerContract
{
    public function __construct(
        private EisCertificateLoaderContract $certLoader,
        private JsonCanonicalizer $canonicalizer,
    ) {
    }

    public function sign(array $payload): SignedEisPayload
    {
        $cert = $this->certLoader->load();

        if ($cert->isExpired()) {
            throw EisCertificateExpiredException::on($cert->subjectCommonName, $cert->notAfterUnix);
        }

        $canonical = $this->canonicalizer->canonicalize($payload);
        $hash      = hash('sha256', $canonical);

        $inputFile  = tempnam(sys_get_temp_dir(), 'eis-in-');
        $outputFile = tempnam(sys_get_temp_dir(), 'eis-sig-');

        if ($inputFile === false || $outputFile === false) {
            throw new EisSigningFailedException('Failed to allocate temp file for PKCS#7 signing.');
        }

        try {
            if (file_put_contents($inputFile, $canonical) === false) {
                throw new EisSigningFailedException('Failed to write canonical bytes for signing.');
            }

            $ok = openssl_pkcs7_sign(
                input_filename:    $inputFile,
                output_filename:   $outputFile,
                certificate:       $cert->certificatePem,
                private_key:       $cert->privateKeyPem,
                headers:           [],
                flags:             PKCS7_DETACHED | PKCS7_BINARY,
            );

            if (! $ok) {
                throw new EisSigningFailedException(
                    'openssl_pkcs7_sign failed: '.(openssl_error_string() ?: 'unknown')
                );
            }

            $signature = file_get_contents($outputFile);
            if ($signature === false || $signature === '') {
                throw new EisSigningFailedException('PKCS#7 signature output was empty.');
            }

            return new SignedEisPayload(
                canonicalBytes: $canonical,
                signaturePem:   $signature,
                payloadHash:    $hash,
                certThumbprint: $cert->thumbprintSha1,
            );
        } finally {
            @unlink($inputFile);
            @unlink($outputFile);
        }
    }
}
