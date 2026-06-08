<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Presentation\Http\Controllers;

use App\Modules\Procurement\Application\Actions\IssueForm2307;
use App\Modules\Procurement\Application\Exceptions\VendorBillNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual 2307 issuance — used when AutoIssueForm2307 listener didn't fire
 * (e.g., bill was imported from legacy data without the event), or to
 * re-issue after a correction.
 *
 * Idempotent: returns the existing 2307 if already issued for the bill.
 *
 *   POST /api/v1/vendor-bills/{bill}/issue-2307
 */
final class IssueForm2307Controller
{
    public function __invoke(
        Request $request,
        IssueForm2307 $action,
        string $bill,
    ): JsonResponse {
        try {
            $form2307Id = $action->execute(
                vendorBillId: $bill,
                actorId:      $request->user()->id,
            );
        } catch (VendorBillNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(['form_2307_id' => $form2307Id], 201);
    }
}
