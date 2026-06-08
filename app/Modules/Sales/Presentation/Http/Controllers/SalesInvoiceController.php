<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Modules\Sales\Infrastructure\Persistence\Eloquent\SalesInvoiceModel;
use App\Modules\Sales\Presentation\Http\Resources\SalesInvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sales invoice resource — only the 7 RESTful methods.
 *
 * Note: store() is intentionally NOT used to issue invoices — issuance is
 * a multi-step workflow (allocate doc_no → compute VAT → post JV →
 * emit event → enqueue EIS) that lives in IssueSalesInvoiceController
 * as a single-action endpoint.
 */
final class SalesInvoiceController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SalesInvoiceModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->string('customer_id'));
        }
        if ($request->filled('from')) {
            $query->where('invoice_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('invoice_date', '<=', $request->date('to'));
        }
        if ($request->boolean('posted_only')) {
            $query->whereNotNull('posted_at');
        }
        if ($request->boolean('voided_only')) {
            $query->whereNotNull('voided_at');
        }

        return SalesInvoiceResource::collection(
            $query->orderByDesc('invoice_date')
                  ->orderByDesc('sequence_no')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $invoice): SalesInvoiceResource
    {
        return new SalesInvoiceResource(
            SalesInvoiceModel::query()
                ->with(['lines', 'customer'])
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($invoice)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use POST /sales-invoices/issue to create a sales invoice.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use POST /sales-invoices/issue (single-action issuance flow).',
        ], 405);
    }

    public function edit(Request $request, string $invoice): JsonResponse
    {
        return new JsonResponse(['message' => 'Posted invoices cannot be edited; void and reissue.'], 423);
    }

    public function update(Request $request, string $invoice): JsonResponse
    {
        return new JsonResponse(['message' => 'Posted invoices cannot be edited; void and reissue.'], 423);
    }

    public function destroy(Request $request, string $invoice): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Sales invoices cannot be deleted (BIR CAS); use POST /sales-invoices/{id}/void.',
        ], 423);
    }
}
