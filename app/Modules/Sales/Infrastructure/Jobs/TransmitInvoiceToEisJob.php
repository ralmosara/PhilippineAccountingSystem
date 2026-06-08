<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Jobs;

use App\Modules\Sales\Application\Contracts\CustomerRepositoryContract;
use App\Modules\Sales\Application\Contracts\SalesInvoiceRepositoryContract;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use App\Modules\Tax\Application\Contracts\EisGatewayClientContract;
use App\Modules\Tax\Application\Contracts\EisPayloadSignerContract;
use App\Modules\Tax\Application\Contracts\SellerProfileProviderContract;
use App\Modules\Tax\Application\DTOs\EisTransmissionResult;
use App\Modules\Tax\Domain\Exceptions\EisCertificateExpiredException;
use App\Modules\Tax\Domain\Services\Eis\EisPayloadBuilder;
use App\Modules\Tax\Infrastructure\Eis\EisQrCodeGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Transmits a sales invoice to BIR EIS (RR 8-2022, RR 6-2024).
 *
 * Flow:
 *   1. Load invoice + customer
 *   2. Resolve seller profile (TIN, registered name, branch code)
 *   3. Build EIS payload (Tax\Domain\Services\Eis\EisPayloadBuilder)
 *   4. Canonicalize + sign with PKCS#7 detached (Pkcs7DetachedSigner)
 *   5. POST to BIR gateway (HttpEisGatewayClient)
 *   6. On 2xx ack: render QR PNG, store on MinIO, mark submission acknowledged
 *   7. On 4xx reject: mark rejected, surface reason to auditor UI
 *   8. On 5xx / network failure: throw to trigger queue retry with backoff
 *
 * Idempotency: re-running on an already-acknowledged submission is a no-op
 * (early-exit on status check); BIR's duplicate-detection on doc_no is the
 * second line of defence.
 *
 * Every attempt — successful or not — writes a row to `tax.eis_retries` so
 * the BIR auditor view can show the full transmission history.
 */
final class TransmitInvoiceToEisJob implements ShouldQueue
{
    use FoundationQueueable;
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> seconds — 5m, 30m, 2h, 6h, 12h */
    public array $backoff = [300, 1800, 7200, 21600, 43200];

    public function __construct(public string $salesInvoiceId)
    {
    }

    public function handle(
        SalesInvoiceRepositoryContract $invoices,
        CustomerRepositoryContract $customers,
        SellerProfileProviderContract $sellers,
        EisPayloadBuilder $builder,
        EisPayloadSignerContract $signer,
        EisGatewayClientContract $gateway,
        EisQrCodeGenerator $qr,
        FilesystemFactory $storage,
    ): void {
        $submission = DB::table('tax.eis_submissions')
            ->where('sales_invoice_id', $this->salesInvoiceId)
            ->lockForUpdate()
            ->first();

        if (! $submission) {
            throw new RuntimeException(
                "EIS submission row not found for invoice {$this->salesInvoiceId}. "
                .'Was the InvoiceIssued event handled?'
            );
        }
        if ($submission->status === 'acknowledged') {
            return;          // idempotent re-runs
        }

        // Load aggregates
        $invoice = $invoices->findById(new SalesInvoiceId($this->salesInvoiceId));
        if ($invoice === null) {
            throw new RuntimeException("SalesInvoice {$this->salesInvoiceId} no longer exists.");
        }
        $customer = $customers->findById($invoice->customerId);
        if ($customer === null) {
            throw new RuntimeException("Customer for invoice {$invoice->docNo} not found.");
        }

        $seller  = $sellers->forCompany($invoice->companyId);
        $payload = $builder->build($invoice, $customer, $seller);

        DB::table('tax.eis_submissions')
            ->where('id', $submission->id)
            ->update([
                'status'       => 'submitting',
                'payload'      => json_encode($payload, JSON_THROW_ON_ERROR),
                'retry_count'  => $submission->retry_count + 1,
                'submitted_at' => now(),
                'updated_at'   => now(),
            ]);

        try {
            $signed = $signer->sign($payload);
        } catch (EisCertificateExpiredException $e) {
            // Don't retry — a new cert won't materialize on its own.
            $this->markFailed($submission->id, $e->getMessage(), terminal: true);
            $this->fail($e);
            return;
        }

        $result = $gateway->transmit($signed);

        $this->recordRetry($submission->id, $result);

        match ($result->outcome) {
            'acknowledged'     => $this->finalizeAcknowledged($submission->id, $signed, $result, $qr, $storage),
            'rejected'         => $this->markFailed($submission->id, $result->rejectionReason ?? 'rejected', terminal: true),
            'transient_error'  => $this->scheduleRetry($submission->id, $result),
        };
    }

    private function finalizeAcknowledged(
        string $submissionId,
        \App\Modules\Tax\Application\DTOs\SignedEisPayload $signed,
        EisTransmissionResult $result,
        EisQrCodeGenerator $qr,
        FilesystemFactory $storage,
    ): void {
        $qrPath = null;
        if ($result->qrUrl !== null) {
            try {
                $png = $qr->generate($result->qrUrl);
                $qrPath = "eis/qr/{$this->salesInvoiceId}.png";
                $storage->disk('minio')->put($qrPath, $png);
            } catch (Throwable $e) {
                Log::warning('EIS QR generation failed — ack still recorded.', [
                    'invoice_id' => $this->salesInvoiceId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        DB::table('tax.eis_submissions')
            ->where('id', $submissionId)
            ->update([
                'status'           => 'acknowledged',
                'bir_ack_no'       => $result->birAckNo,
                'qr_url'           => $result->qrUrl,
                'qr_image_path'    => $qrPath,
                'signature'        => $signed->signaturePem,
                'payload_hash'     => $signed->payloadHash,
                'cert_thumbprint'  => $signed->certThumbprint,
                'acknowledged_at'  => now(),
                'rejection_reason' => null,
                'updated_at'       => now(),
            ]);
    }

    private function markFailed(string $submissionId, string $reason, bool $terminal): void
    {
        DB::table('tax.eis_submissions')
            ->where('id', $submissionId)
            ->update([
                'status'           => $terminal ? 'rejected' : 'failed',
                'rejection_reason' => $reason,
                'updated_at'       => now(),
            ]);
    }

    private function scheduleRetry(string $submissionId, EisTransmissionResult $result): void
    {
        DB::table('tax.eis_submissions')
            ->where('id', $submissionId)
            ->update([
                'status'     => 'pending',
                'updated_at' => now(),
            ]);

        // Throwing schedules the queued retry with the configured $backoff.
        throw new RuntimeException(
            "BIR EIS transient error (HTTP {$result->httpStatus}): {$result->rejectionReason}"
        );
    }

    private function recordRetry(string $submissionId, EisTransmissionResult $result): void
    {
        DB::table('tax.eis_retries')->insert([
            'eis_submission_id' => $submissionId,
            'attempted_at'      => now(),
            'http_status'       => $result->httpStatus,
            'response_body'     => $result->rawResponseBody,
            'error_message'     => $result->rejectionReason,
        ]);
    }

    /** Called by the queue worker when $tries is exhausted. */
    public function failed(Throwable $e): void
    {
        DB::table('tax.eis_submissions')
            ->where('sales_invoice_id', $this->salesInvoiceId)
            ->update([
                'status'           => 'failed',
                'rejection_reason' => 'Retry budget exhausted: '.$e->getMessage(),
                'updated_at'       => now(),
            ]);
    }
}
