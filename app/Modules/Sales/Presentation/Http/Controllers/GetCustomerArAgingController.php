<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Modules\Sales\Application\Queries\GetCustomerArAging;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/customers/{customer}/ar-aging?as_of=YYYY-MM-DD
 *
 * Returns the AR-aging snapshot for one customer. Replaces the client-side
 * computation in CustomerDetailPage with a single authoritative server query.
 *
 * Permission: sales.invoices.view (anyone who can see invoices can see aging).
 */
final class GetCustomerArAgingController
{
    public function __invoke(
        Request $request,
        string $customer,
        GetCustomerArAging $query,
    ): JsonResponse {
        abort_unless(
            $request->user()->can('sales.invoices.view'),
            403,
            'Missing sales.invoices.view permission.',
        );

        $asOfRaw = (string) $request->query('as_of', date('Y-m-d'));
        $asOf    = new DateTimeImmutable($asOfRaw);

        $aging = $query->execute($customer, $asOf);

        return new JsonResponse([
            'customer_id'           => $aging->customerId,
            'as_of'                 => $aging->asOf->format('Y-m-d'),
            'buckets' => [
                'current'        => $aging->current,
                'd1_30'          => $aging->bucket_1_30,
                'd31_60'         => $aging->bucket_31_60,
                'd61_90'         => $aging->bucket_61_90,
                'd91_plus'       => $aging->bucket_91_plus,
            ],
            'total'                 => $aging->total,
            'unpaid_invoice_count'  => $aging->unpaidInvoiceCount,
        ]);
    }
}
