<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\OsdElectionRepositoryContract;
use App\Modules\Tax\Domain\Entities\OsdElection;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;
use DateTimeImmutable;

/**
 * Idempotent helper called by every ITR generator (Q-return and annual)
 * BEFORE the form is persisted:
 *
 *   - If an active election exists for (company, year, taxpayer_type):
 *       → assert the supplied regime matches; throw on mismatch.
 *       → return the existing election unchanged.
 *
 *   - If no active election exists:
 *       → record one with regime, declared_in_form_type, declared_in_quarter.
 *       → audit-write 'osd_election.locked'.
 *       → return the new election.
 *
 * Action layer is responsible for translating its boolean flags
 * (use_osd, elect_flat_8pct) into the three-way regime string.
 */
final readonly class RecordOrConfirmOsdElection
{
    public function __construct(
        private OsdElectionRepositoryContract $repo,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(
        string $companyId,
        int $fiscalYear,
        string $taxpayerType,           // 'individual' | 'corporate'
        string $regime,                  // 'itemized' | 'osd' | 'flat_8pct'
        string $declaredInFormType,
        ?int $declaredInQuarter,
        ?string $declaredInBirFormId,
        string $actorId,
    ): OsdElection {
        $active = $this->repo->findActive($companyId, $fiscalYear, $taxpayerType);

        if ($active !== null) {
            $active->assertMatches($regime);     // throws OsdElectionMismatchException on conflict
            return $active;
        }

        $election = new OsdElection(
            id:                    OsdElectionId::generate(),
            companyId:             $companyId,
            fiscalYear:            $fiscalYear,
            taxpayerType:          $taxpayerType,
            regime:                $regime,
            declaredInFormType:    $declaredInFormType,
            declaredInQuarter:     $declaredInQuarter,
            declaredInBirFormId:   $declaredInBirFormId,
            lockedAt:              new DateTimeImmutable(),
            lockedBy:              $actorId,
        );

        $this->repo->save($election);

        $this->audit->writeEvent(
            actorId:     $actorId,
            companyId:   $companyId,
            eventType:   'osdelection.locked',
            aggregate:   'OsdElection',
            aggregateId: $election->id->value,
            payload: [
                'fiscal_year'           => $fiscalYear,
                'taxpayer_type'         => $taxpayerType,
                'regime'                => $regime,
                'declared_in_form_type' => $declaredInFormType,
                'declared_in_quarter'   => $declaredInQuarter,
            ],
        );

        return $election;
    }
}
