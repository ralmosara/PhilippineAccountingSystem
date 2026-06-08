<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Eis;

use App\Modules\Tax\Application\Contracts\EisGatewayClientContract;
use App\Modules\Tax\Application\DTOs\EisTransmissionResult;
use App\Modules\Tax\Application\DTOs\SignedEisPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * BIR EIS issuance gateway client.
 *
 * Wire format (RR 8-2022 § 5, confirmed via sandbox onboarding):
 *
 *   POST {base_url}/api/v1/invoices
 *   Authorization: Bearer <oauth_token>      ← obtained out-of-band
 *   Content-Type:  application/json
 *
 *   {
 *     "payload":         <canonical JSON, as a JSON string field>,
 *     "signature":       <base64 PKCS#7 detached>,
 *     "cert_thumbprint": "AA:BB:CC:..." (sha1),
 *     "payload_hash":    "<sha256 hex of canonical bytes>",
 *     "submitted_at":    "<RFC3339>"
 *   }
 *
 * Response (success):  { "ack_no": "...", "qr_url": "https://..." }
 * Response (rejected): { "error_code": "...", "error_message": "..." }
 *
 * 5xx + network failures are reported as `transient_error` so the caller
 * re-enqueues with backoff. 4xx is final-rejected (we never retry into a 4xx).
 */
final readonly class HttpEisGatewayClient implements EisGatewayClientContract
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private string $bearerToken,
        private int $timeoutSeconds = 30,
        private string $issuancePath = '/api/v1/invoices',
    ) {
    }

    public function transmit(SignedEisPayload $signed): EisTransmissionResult
    {
        $body = [
            'payload'         => $signed->canonicalBytes,
            'signature'       => base64_encode($signed->signaturePem),
            'cert_thumbprint' => $signed->certThumbprint,
            'payload_hash'    => $signed->payloadHash,
            'submitted_at'    => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        try {
            $response = $this->http
                ->withToken($this->bearerToken)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeoutSeconds)
                ->post(rtrim($this->baseUrl, '/').$this->issuancePath, $body);
        } catch (ConnectionException $e) {
            return EisTransmissionResult::transientError(
                httpStatus: null,
                reason:     'Network failure reaching BIR EIS: '.$e->getMessage(),
            );
        }

        return $this->mapResponse($response);
    }

    private function mapResponse(Response $response): EisTransmissionResult
    {
        $raw    = (string) $response->body();
        $status = $response->status();

        // 2xx → acknowledged
        if ($response->successful()) {
            $json   = $response->json() ?? [];
            $ackNo  = is_string($json['ack_no'] ?? null) ? $json['ack_no'] : null;
            $qrUrl  = is_string($json['qr_url'] ?? null) ? $json['qr_url'] : null;

            if ($ackNo === null || $ackNo === '') {
                return EisTransmissionResult::transientError(
                    $status,
                    'BIR responded 2xx but no ack_no in body.',
                    $raw,
                );
            }
            return EisTransmissionResult::acknowledged($status, $ackNo, $qrUrl, $raw);
        }

        // 4xx → permanent rejection (validation, auth, duplicate submission, …)
        if ($response->clientError()) {
            $json    = $response->json() ?? [];
            $message = $json['error_message']
                ?? $json['message']
                ?? "BIR rejected submission with HTTP {$status}";
            return EisTransmissionResult::rejected($status, (string) $message, $raw);
        }

        // 5xx / unknown → retry
        return EisTransmissionResult::transientError(
            $status,
            "BIR EIS upstream error: HTTP {$status}",
            $raw,
        );
    }
}
