<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Eis;

use App\Modules\Tax\Application\Contracts\EisGatewayClientContract;
use App\Modules\Tax\Application\DTOs\EisTransmissionResult;
use App\Modules\Tax\Application\DTOs\SignedEisPayload;

/**
 * In-memory EIS gateway used in:
 *   - local dev (when BIR_EIS_ENABLED=false)
 *   - feature tests
 *   - sandbox-unavailable smoke runs
 *
 * Behaviour is programmable:
 *   - default mode = acknowledge everything with a fake ack_no
 *   - set ::failNextWith(int $http) to simulate a rejection or upstream error
 *
 * Recorded calls are inspectable via ::calls() for assertions.
 */
final class FakeEisGatewayClient implements EisGatewayClientContract
{
    /** @var list<SignedEisPayload> */
    private array $calls = [];

    /** @var list<EisTransmissionResult> queued responses; consumed FIFO. */
    private array $queued = [];

    public function transmit(SignedEisPayload $signed): EisTransmissionResult
    {
        $this->calls[] = $signed;

        if ($this->queued !== []) {
            return array_shift($this->queued);
        }

        // Default: acknowledge with a deterministic fake ack number tied to payload hash
        $ack = 'FAKE-'.strtoupper(substr($signed->payloadHash, 0, 12));
        return EisTransmissionResult::acknowledged(
            httpStatus: 200,
            birAckNo:   $ack,
            qrUrl:      "https://eis-sandbox.bir.gov.ph/invoice/{$ack}",
            rawBody:    json_encode(['ack_no' => $ack, 'qr_url' => "fake://{$ack}"]),
        );
    }

    public function queue(EisTransmissionResult $result): void
    {
        $this->queued[] = $result;
    }

    public function failNextWithRejection(string $reason = 'Test rejection'): void
    {
        $this->queue(EisTransmissionResult::rejected(400, $reason, '{"error_message":"'.$reason.'"}'));
    }

    public function failNextWithTransient(string $reason = 'Test 503'): void
    {
        $this->queue(EisTransmissionResult::transientError(503, $reason, '{"error":"upstream"}'));
    }

    /** @return list<SignedEisPayload> */
    public function calls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        $this->calls  = [];
        $this->queued = [];
    }
}
