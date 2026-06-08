<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\OsdElectionRepositoryContract;
use App\Modules\Tax\Application\Exceptions\SameRegimeSupersedeException;
use App\Modules\Tax\Domain\Entities\OsdElection;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Amends a year's deduction-regime election. RR 2-2010 § 7 forbids casual
 * mid-year switching, but BIR will accept a written amendment of the
 * affected quarterly return — and once that paperwork is filed, our
 * canonical row needs to reflect the new regime so subsequent generators
 * use it.
 *
 * What this action does (atomically, inside one DB transaction):
 *
 *   1. Validate: the new regime must DIFFER from the current one
 *      (otherwise the call is meaningless — a no-op pretending to be a fix).
 *   2. Validate: the targeted election must be currently active
 *      (you cannot supersede an already-superseded row; chain forward instead).
 *   3. Mark the old election superseded_at = now() with the operator's reason.
 *   4. Insert a fresh election row with regime = $newRegime, replaces_id
 *      pointing at the old row, declared_in_form_type = 'amendment'.
 *   5. Emit an `osdelection.superseded` audit event listing every BIR form
 *      that consumed the old regime so the reviewer knows which Q-returns
 *      now need to be refiled as "Amended Return = Yes" with BIR.
 *
 * What this action explicitly does NOT do:
 *
 *   - It does NOT re-render the affected ITRs. The taxpayer must regenerate
 *     each one and submit them to BIR as amendments — this action only
 *     unblocks that workflow.
 *   - It does NOT validate that BIR has approved the amendment. The 'reason'
 *     field is the auditable record; BIR's signed approval letter is filed
 *     by the user in the documents module.
 */
final readonly class SupersedeOsdElection
{
    public function __construct(
        private OsdElectionRepositoryContract $repo,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(
        OsdElectionId $currentElectionId,
        string $newRegime,                  // 'itemized' | 'osd' | 'flat_8pct'
        string $reason,
        string $actorId,
    ): OsdElection {
        return DB::transaction(function () use ($currentElectionId, $newRegime, $reason, $actorId) {
            $current = $this->repo->findById($currentElectionId);
            if ($current === null) {
                throw new DomainException("OSD election {$currentElectionId} not found.");
            }
            if (! $current->isActive()) {
                throw new DomainException(
                    "OSD election {$currentElectionId} is already superseded by another amendment. "
                    ."Chain forward from the active election instead."
                );
            }
            if ($newRegime === $current->regime) {
                throw new SameRegimeSupersedeException(
                    "Supersede target regime '{$newRegime}' is identical to the current "
                    ."election's regime. An amendment must change the regime; "
                    ."if you only want to attach BIR's approval letter, use the documents endpoint."
                );
            }

            // 1. Mark current superseded (entity throws on bad input)
            $current->supersede($reason);
            $this->repo->save($current);

            // 2. Insert successor pointing back at the original
            $successor = new OsdElection(
                id:                    OsdElectionId::generate(),
                companyId:             $current->companyId,
                fiscalYear:            $current->fiscalYear,
                taxpayerType:          $current->taxpayerType,
                regime:                $newRegime,
                declaredInFormType:    'amendment',
                declaredInQuarter:     null,
                declaredInBirFormId:   null,
                lockedAt:              new DateTimeImmutable(),
                lockedBy:              $actorId,
                replacesId:            $current->id,
            );
            $this->repo->save($successor);

            // 3. Audit
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $current->companyId,
                eventType:   'osdelection.superseded',
                aggregate:   'OsdElection',
                aggregateId: $current->id->value,
                payload: [
                    'fiscal_year'    => $current->fiscalYear,
                    'taxpayer_type'  => $current->taxpayerType,
                    'old_regime'     => $current->regime,
                    'new_regime'     => $newRegime,
                    'successor_id'   => $successor->id->value,
                    'reason'         => $reason,
                    // Hint to the reviewer: any ITR already generated under the
                    // old regime is now stale and must be refiled.
                    'requires_amended_refile' => true,
                ],
            );

            return $successor;
        });
    }
}
