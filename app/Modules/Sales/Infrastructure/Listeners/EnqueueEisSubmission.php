<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Listeners;

use App\Modules\Sales\Domain\Events\InvoiceIssued;
use App\Modules\Sales\Infrastructure\Jobs\TransmitInvoiceToEisJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Listens for InvoiceIssued and creates a tax.eis_submissions row in
 * status='pending', then dispatches TransmitInvoiceToEisJob to the
 * 'eis-priority' Horizon queue.
 *
 * No-op when BIR_EIS_ENABLED=false (default in dev). The row is still
 * created so we can backfill later if EIS is enabled.
 */
final class EnqueueEisSubmission implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(InvoiceIssued $event): void
    {
        $payload = [
            'invoice_id'   => $event->salesInvoiceId,
            'doc_no'       => $event->docNo,
            'customer_id'  => $event->customerId,
            'invoice_date' => $event->invoiceDate->format('Y-m-d'),
            'total'        => $event->total,
            'company_id'   => $event->companyId,
        ];

        DB::table('tax.eis_submissions')->insertOrIgnore([
            'id'              => Uuid::uuid4()->toString(),
            'sales_invoice_id' => $event->salesInvoiceId,
            'payload'         => json_encode($payload, JSON_THROW_ON_ERROR),
            'status'          => 'pending',
            'retry_count'     => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        if (config('services.bir_eis.enabled', false)) {
            TransmitInvoiceToEisJob::dispatch($event->salesInvoiceId)
                ->onQueue('eis-priority');
        }
    }
}
