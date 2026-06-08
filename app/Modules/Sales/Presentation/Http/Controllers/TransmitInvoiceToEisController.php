<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Modules\Sales\Infrastructure\Jobs\TransmitInvoiceToEisJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual EIS retransmit — used by the auditor UI to retry a rejected/failed
 * submission, or by ops if the automatic listener didn't enqueue.
 *
 *   POST /api/v1/sales-invoices/{invoice}/eis/transmit
 */
final class TransmitInvoiceToEisController
{
    public function __invoke(Request $request, string $invoice): JsonResponse
    {
        TransmitInvoiceToEisJob::dispatch($invoice)->onQueue('eis-priority');

        return new JsonResponse(['message' => 'EIS transmission queued.'], 202);
    }
}
