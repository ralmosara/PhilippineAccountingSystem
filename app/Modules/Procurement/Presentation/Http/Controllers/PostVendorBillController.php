<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Controllers;

use App\Modules\Procurement\Application\Actions\PostVendorBill;
use App\Modules\Procurement\Application\Exceptions\VendorNotFoundException;
use App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorBillModel;
use App\Modules\Procurement\Presentation\Http\Requests\PostVendorBillRequest;
use App\Modules\Procurement\Presentation\Http\Resources\VendorBillResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Single-action: post a vendor bill end-to-end.
 *   POST /api/v1/vendor-bills/post
 *
 * Computes withholding tax → posts JV → emits VendorBillPosted →
 * AutoIssueForm2307 listener fires → 2307 record created automatically.
 */
final class PostVendorBillController
{
    public function __invoke(
        PostVendorBillRequest $request,
        PostVendorBill $action,
    ): JsonResponse {
        try {
            $bill = $action->execute(
                companyId:                    $request->user()->company_id,
                vendorId:                     $request->string('vendor_id')->toString(),
                purchaseOrderId:              $request->input('purchase_order_id'),
                vendorInvoiceNo:              $request->string('vendor_invoice_no')->toString(),
                vendorInvoiceDate:            new DateTimeImmutable($request->string('vendor_invoice_date')->toString()),
                billDate:                     new DateTimeImmutable($request->string('bill_date')->toString()),
                lines:                        $request->validated('lines'),
                apAccountId:                  $request->string('ap_account_id')->toString(),
                vatInputAccountId:            $request->string('vat_input_account_id')->toString(),
                withholdingPayableAccountId:  $request->string('withholding_payable_account_id')->toString(),
                jvDocumentSeriesId:           $request->string('jv_document_series_id')->toString(),
                actorId:                      $request->user()->id,
                atcCodeOverride:              $request->input('atc_code_override'),
                dueDate:                      $request->filled('due_date')
                                                  ? new DateTimeImmutable($request->string('due_date')->toString())
                                                  : null,
                currency:                     $request->string('currency', 'PHP')->toString(),
            );
        } catch (VendorNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        }

        $model = VendorBillModel::query()->with(['lines', 'vendor'])->findOrFail($bill->id->value);
        return new JsonResponse(new VendorBillResource($model), 201);
    }
}
