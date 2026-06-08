<?php

declare(strict_types=1);

use App\Modules\Tax\Application\Contracts\EisCertificateLoaderContract;
use App\Modules\Tax\Application\DTOs\LoadedCertificate;
use App\Modules\Tax\Domain\Exceptions\EisCertificateExpiredException;
use App\Modules\Tax\Domain\Services\Eis\JsonCanonicalizer;
use App\Modules\Tax\Infrastructure\Eis\Pkcs7DetachedSigner;

/**
 * Generates a throwaway X.509 + RSA keypair at test time and verifies the
 * sign → re-verify roundtrip. The point isn't to test OpenSSL; the point is
 * to confirm:
 *   1. our canonical bytes are what end up in the PKCS#7 envelope
 *   2. an expired cert is refused before signing
 *   3. the SignedEisPayload carries the right hash + thumbprint
 */
function generateSelfSignedCert(int $notAfterUnix = null): array
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    expect($key)->not->toBeFalse('openssl_pkey_new failed; ext-openssl missing?');

    $csr = openssl_csr_new(['commonName' => 'PHA Test Signing Cert'], $key);
    expect($csr)->not->toBeFalse();

    // Use a positive validity by default; allow caller to backdate for expiry tests.
    $days = $notAfterUnix === null
        ? 365
        : max(1, (int) ceil(($notAfterUnix - time()) / 86400));

    $x509 = openssl_csr_sign($csr, null, $key, $days);
    expect($x509)->not->toBeFalse();

    openssl_x509_export($x509, $certPem);
    openssl_pkey_export($key, $keyPem);

    $parsed = openssl_x509_parse($x509);

    return [
        'cert_pem'        => $certPem,
        'key_pem'         => $keyPem,
        'not_after_unix'  => (int) $parsed['validTo_time_t'],
        'thumbprint_sha1' => strtoupper(hash('sha1', base64_decode(
            preg_replace('/-----[^-]+-----|\s+/', '', $certPem) ?? '',
        ))),
    ];
}

function fakeCertLoader(string $certPem, string $keyPem, int $notAfter, string $thumbprint): EisCertificateLoaderContract
{
    return new class($certPem, $keyPem, $notAfter, $thumbprint) implements EisCertificateLoaderContract {
        public function __construct(
            private string $certPem,
            private string $keyPem,
            private int $notAfter,
            private string $thumbprint,
        ) {
        }

        public function load(): LoadedCertificate
        {
            return new LoadedCertificate(
                certificatePem:    $this->certPem,
                privateKeyPem:     $this->keyPem,
                thumbprintSha1:    $this->thumbprint,
                subjectCommonName: 'PHA Test Signing Cert',
                notAfterUnix:      $this->notAfter,
            );
        }
    };
}

it('signs a canonical payload and produces a verifiable detached signature', function () {
    $gen    = generateSelfSignedCert();
    $signer = new Pkcs7DetachedSigner(
        fakeCertLoader($gen['cert_pem'], $gen['key_pem'], $gen['not_after_unix'], $gen['thumbprint_sha1']),
        new JsonCanonicalizer(),
    );

    $signed = $signer->sign(['doc_no' => 'SI-2026-000001', 'amount' => '11200.0000']);

    // canonical bytes are exactly the sorted, slash-unescaped JSON
    expect($signed->canonicalBytes)
        ->toBe('{"amount":"11200.0000","doc_no":"SI-2026-000001"}')
        ->and($signed->payloadHash)
            ->toBe(hash('sha256', $signed->canonicalBytes))
        ->and($signed->certThumbprint)->toBe($gen['thumbprint_sha1'])
        ->and($signed->signaturePem)->toStartWith('-----BEGIN PKCS7-----')
            ->or->toContain('-----BEGIN CMS-----');           // OpenSSL variants

    // Verify: write payload + signature to temp files and run openssl_pkcs7_verify
    $payloadFile = tempnam(sys_get_temp_dir(), 'verify-payload-');
    $sigFile     = tempnam(sys_get_temp_dir(), 'verify-sig-');
    $certFile    = tempnam(sys_get_temp_dir(), 'verify-cert-');
    file_put_contents($payloadFile, $signed->canonicalBytes);
    file_put_contents($sigFile, $signed->signaturePem);
    file_put_contents($certFile, $gen['cert_pem']);

    $ok = openssl_pkcs7_verify(
        $sigFile,
        PKCS7_NOVERIFY | PKCS7_BINARY | PKCS7_DETACHED,
        '/dev/null',
        [],                    // trust store empty — PKCS7_NOVERIFY skips chain
        $certFile,
        $payloadFile,
    );

    expect($ok)->toBeTrue('PKCS#7 signature did not verify against the signed payload.');

    @unlink($payloadFile);
    @unlink($sigFile);
    @unlink($certFile);
})->skip(! function_exists('openssl_pkcs7_sign'), 'ext-openssl required');

it('refuses to sign with an expired certificate', function () {
    $gen = generateSelfSignedCert();
    // Force the loader to report the cert as expired (yesterday).
    $signer = new Pkcs7DetachedSigner(
        fakeCertLoader($gen['cert_pem'], $gen['key_pem'], time() - 86400, $gen['thumbprint_sha1']),
        new JsonCanonicalizer(),
    );

    expect(fn () => $signer->sign(['doc_no' => 'SI-2026-000001']))
        ->toThrow(EisCertificateExpiredException::class);
})->skip(! function_exists('openssl_pkey_new'), 'ext-openssl required');

it('produces a stable signature input — same payload → same canonical bytes → same hash', function () {
    $gen    = generateSelfSignedCert();
    $signer = new Pkcs7DetachedSigner(
        fakeCertLoader($gen['cert_pem'], $gen['key_pem'], $gen['not_after_unix'], $gen['thumbprint_sha1']),
        new JsonCanonicalizer(),
    );

    $payload = ['z' => 1, 'a' => 2];
    $shuffled = ['a' => 2, 'z' => 1];

    expect($signer->sign($payload)->payloadHash)
        ->toBe($signer->sign($shuffled)->payloadHash);
    // Note: PKCS#7 signatures embed a timestamp / random salt by default, so
    // signaturePem itself is NOT byte-stable. The *signed-input* hash is.
})->skip(! function_exists('openssl_pkey_new'), 'ext-openssl required');
