<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\DTOs;

/**
 * Outcome of a single POST to BIR EIS. Maps directly onto the columns
 * we persist to `tax.eis_submissions` + `tax.eis_retries`.
 */
final readonly class EisTransmissionResult
{
    private function __construct(
        public string $outcome,                 // 'acknowledged' | 'rejected' | 'transient_error'
        public ?int $httpStatus,
        public ?string $birAckNo,
        public ?string $qrUrl,
        public ?string $rejectionReason,
        public ?string $rawResponseBody,
    ) {
    }

    public static function acknowledged(int $httpStatus, string $birAckNo, ?string $qrUrl, string $rawBody): self
    {
        return new self('acknowledged', $httpStatus, $birAckNo, $qrUrl, null, $rawBody);
    }

    public static function rejected(int $httpStatus, string $reason, string $rawBody): self
    {
        return new self('rejected', $httpStatus, null, null, $reason, $rawBody);
    }

    /** 5xx or network failure — re-enqueue with backoff. */
    public static function transientError(?int $httpStatus, string $reason, ?string $rawBody = null): self
    {
        return new self('transient_error', $httpStatus, null, null, $reason, $rawBody);
    }

    public function shouldRetry(): bool
    {
        return $this->outcome === 'transient_error';
    }

    public function isAcknowledged(): bool
    {
        return $this->outcome === 'acknowledged';
    }
}
