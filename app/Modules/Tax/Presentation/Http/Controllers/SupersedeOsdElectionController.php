<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\SupersedeOsdElection;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\OsdElectionModel;
use App\Modules\Tax\Presentation\Http\Requests\SupersedeOsdElectionRequest;
use App\Modules\Tax\Presentation\Http\Resources\OsdElectionResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/v1/tax/osd-elections/{election}/supersede
 *
 * Files a BIR-permitted amendment to a year's deduction-regime election.
 * Marks the current election superseded and chains a successor row with
 * the new regime. The audit trail records the new_regime, reason, and a
 * `requires_amended_refile` flag so the reviewer knows which ITRs to refile.
 */
final class SupersedeOsdElectionController
{
    public function __invoke(
        SupersedeOsdElectionRequest $request,
        string $election,
        SupersedeOsdElection $action,
    ): JsonResponse {
        // Pre-flight tenant scope — the action is module-agnostic and trusts
        // its caller. The HTTP layer is where we enforce company_id.
        $row = OsdElectionModel::query()
            ->where('company_id', $request->user()->company_id)
            ->find($election);
        if ($row === null) {
            throw new NotFoundHttpException();
        }

        $successor = $action->execute(
            currentElectionId: new OsdElectionId($election),
            newRegime:         (string) $request->string('new_regime'),
            reason:            (string) $request->string('reason'),
            actorId:           (string) $request->user()->id,
        );

        $successorModel = OsdElectionModel::query()->findOrFail($successor->id->value);
        return new JsonResponse(new OsdElectionResource($successorModel), 201);
    }
}
