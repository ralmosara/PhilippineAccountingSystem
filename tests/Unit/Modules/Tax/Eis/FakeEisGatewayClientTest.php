<?php

declare(strict_types=1);

use App\Modules\Tax\Application\DTOs\SignedEisPayload;
use App\Modules\Tax\Infrastructure\Eis\FakeEisGatewayClient;

/**
 * The Fake exists so dev/staging/CI exercise the entire EIS pipeline without
 * hitting BIR. The contract it must honour:
 *   - default behaviour: acknowledge every submission
 *   - queue() injects scripted responses (FIFO)
 *   - calls() records every payload submitted, for assertions
 */
beforeEach(function () {
    $this->client = new FakeEisGatewayClient();
    $this->payload = new SignedEisPayload(
        canonicalBytes: '{"doc_no":"SI-2026-000001"}',
        signaturePem:   '-----BEGIN PKCS7-----stub-----END PKCS7-----',
        payloadHash:    hash('sha256', '{"doc_no":"SI-2026-000001"}'),
        certThumbprint: 'AABBCC',
    );
});

it('acknowledges by default with a deterministic ack number tied to payload hash', function () {
    $result = $this->client->transmit($this->payload);

    expect($result->isAcknowledged())->toBeTrue()
        ->and($result->httpStatus)->toBe(200)
        ->and($result->birAckNo)->toStartWith('FAKE-')
        ->and($result->qrUrl)->toContain('eis-sandbox.bir.gov.ph');
});

it('returns the same ack_no for the same payload (idempotent fake)', function () {
    $a = $this->client->transmit($this->payload);
    $b = $this->client->transmit($this->payload);

    expect($a->birAckNo)->toBe($b->birAckNo);
});

it('honours queued responses in FIFO order', function () {
    $this->client->failNextWithRejection('Validation failed: duplicate doc_no');
    $this->client->failNextWithTransient('Upstream timeout');

    $first  = $this->client->transmit($this->payload);
    $second = $this->client->transmit($this->payload);
    $third  = $this->client->transmit($this->payload);     // queue empty → default ack

    expect($first->outcome)->toBe('rejected')
        ->and($first->rejectionReason)->toContain('duplicate doc_no')
        ->and($second->outcome)->toBe('transient_error')
        ->and($second->shouldRetry())->toBeTrue()
        ->and($third->isAcknowledged())->toBeTrue();
});

it('records every transmission call for assertion in feature tests', function () {
    $this->client->transmit($this->payload);
    $this->client->transmit($this->payload);

    expect($this->client->calls())->toHaveCount(2)
        ->and($this->client->calls()[0]->canonicalBytes)
            ->toBe('{"doc_no":"SI-2026-000001"}');
});

it('reset() clears both calls and queued responses', function () {
    $this->client->transmit($this->payload);
    $this->client->failNextWithRejection();
    $this->client->reset();

    expect($this->client->calls())->toHaveCount(0);

    // Queue was cleared too, so next call returns the default ack (not the rejection)
    $next = $this->client->transmit($this->payload);
    expect($next->isAcknowledged())->toBeTrue();
});

it('transient errors signal shouldRetry; acknowledgements and rejections do not', function () {
    expect(\App\Modules\Tax\Application\DTOs\EisTransmissionResult::acknowledged(200, 'ACK', null, '{}')->shouldRetry())->toBeFalse()
        ->and(\App\Modules\Tax\Application\DTOs\EisTransmissionResult::rejected(400, 'bad', '{}')->shouldRetry())->toBeFalse()
        ->and(\App\Modules\Tax\Application\DTOs\EisTransmissionResult::transientError(503, 'flaky')->shouldRetry())->toBeTrue();
});
