<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/v1/tax/form-2307-received/{form}/reject
 *
 * Marks a 'recorded' certificate as 'rejected' — excluded from future credit
 * aggregation. Used when:
 *   - the payor sends a corrected version (book the new one, reject the old)
 *   - our reviewer spots a TIN / amount mismatch with bank deposit records
 *   - BIR audit flags it as invalid
 *
 * Refused if the cert was already claimed in a filed ITR; amend the ITR first.
 */
final class RejectForm2307ReceivedController
{
    public function __invoke(
        Request $request,
        string $form,
        Form2307ReceivedRepositoryContract $repo,
        AuditWriterContract $audit,
    ): JsonResponse {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $cert = $repo->findById(new Form2307ReceivedId($form));
        if ($cert === null || $cert->companyId !== $request->user()->company_id) {
            throw new NotFoundHttpException();
        }

        $cert->reject((string) $request->string('reason'));
        $repo->save($cert);

        $audit->writeEvent(
            actorId:     (string) $request->user()->id,
            companyId:   $cert->companyId,
            eventType:   'form2307received.rejected',
            aggregate:   'Form2307Received',
            aggregateId: $cert->id->value,
            payload:     ['reason' => $cert->rejectionReason],
            ipAddress:   $request->ip(),
            userAgent:   $request->userAgent(),
        );

        return new JsonResponse([
            'id'               => $cert->id->value,
            'status'           => $cert->status,
            'rejection_reason' => $cert->rejectionReason,
        ]);
    }
}
