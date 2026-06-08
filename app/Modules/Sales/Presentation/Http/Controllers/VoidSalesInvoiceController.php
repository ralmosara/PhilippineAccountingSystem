<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Modules\Sales\Application\Actions\VoidSalesInvoice;
use App\Modules\Sales\Application\Exceptions\InvoiceNotFoundException;
use App\Modules\Sales\Domain\Exceptions\InvoiceAlreadyVoidedException;
use App\Modules\Sales\Presentation\Http\Requests\VoidSalesInvoiceRequest;
use Illuminate\Http\JsonResponse;

final class VoidSalesInvoiceController
{
    public function __invoke(
        VoidSalesInvoiceRequest $request,
        VoidSalesInvoice $action,
        string $invoice,
    ): JsonResponse {
        try {
            $action->execute(
                salesInvoiceId: $invoice,
                reason:         $request->string('reason')->toString(),
                actorId:        $request->user()->id,
            );
        } catch (InvoiceNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (InvoiceAlreadyVoidedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }

        return new JsonResponse(['message' => 'Invoice voided.'], 200);
    }
}
