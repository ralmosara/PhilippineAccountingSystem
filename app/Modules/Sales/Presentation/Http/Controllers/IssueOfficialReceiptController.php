<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Modules\Sales\Application\Actions\IssueOfficialReceipt;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\OfficialReceiptModel;
use App\Modules\Sales\Presentation\Http\Requests\IssueOfficialReceiptRequest;
use App\Modules\Sales\Presentation\Http\Resources\OfficialReceiptResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class IssueOfficialReceiptController
{
    public function __invoke(
        IssueOfficialReceiptRequest $request,
        IssueOfficialReceipt $action,
    ): JsonResponse {
        $receipt = $action->execute(
            companyId:        $request->user()->company_id,
            customerId:       $request->string('customer_id')->toString(),
            salesInvoiceId:   $request->input('sales_invoice_id'),
            documentSeriesId: $request->string('document_series_id')->toString(),
            receivedDate:     new DateTimeImmutable($request->string('received_date')->toString()),
            amount:           $request->string('amount')->toString(),
            cashAccountId:    $request->string('cash_account_id')->toString(),
            arAccountId:      $request->string('ar_account_id')->toString(),
            paymentMethod:    $request->string('payment_method')->toString(),
            actorId:          $request->user()->id,
            referenceNo:      $request->input('reference_no'),
            remarks:          $request->input('remarks'),
        );

        $model = OfficialReceiptModel::findOrFail($receipt->id->value);
        return new JsonResponse(new OfficialReceiptResource($model), 201);
    }
}
