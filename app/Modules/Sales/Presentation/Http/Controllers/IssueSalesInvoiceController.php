<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Modules\Sales\Application\Actions\IssueSalesInvoice;
use App\Modules\Sales\Application\Exceptions\CustomerNotFoundException;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\SalesInvoiceModel;
use App\Modules\Sales\Presentation\Http\Requests\IssueSalesInvoiceRequest;
use App\Modules\Sales\Presentation\Http\Resources\SalesInvoiceResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Single-action: issue a sales invoice end-to-end.
 *   POST /api/v1/sales-invoices/issue
 */
final class IssueSalesInvoiceController
{
    public function __invoke(
        IssueSalesInvoiceRequest $request,
        IssueSalesInvoice $action,
    ): JsonResponse {
        try {
            $invoice = $action->execute(
                companyId:           $request->user()->company_id,
                customerId:          $request->string('customer_id')->toString(),
                documentSeriesId:    $request->string('document_series_id')->toString(),
                invoiceDate:         new DateTimeImmutable($request->string('invoice_date')->toString()),
                docKind:             $request->string('doc_kind', 'charge')->toString(),
                lines:               $request->validated('lines'),
                arAccountId:         $request->string('ar_account_id')->toString(),
                vatPayableAccountId: $request->string('vat_payable_account_id')->toString(),
                actorId:             $request->user()->id,
                dueDate:             $request->filled('due_date')
                                        ? new DateTimeImmutable($request->string('due_date')->toString())
                                        : null,
                currency:            $request->string('currency', 'PHP')->toString(),
            );
        } catch (CustomerNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        }

        // Re-load through Eloquent so the response carries the persisted state
        $model = SalesInvoiceModel::query()->with(['lines', 'customer'])->findOrFail($invoice->id->value);

        return new JsonResponse(new SalesInvoiceResource($model), 201);
    }
}
