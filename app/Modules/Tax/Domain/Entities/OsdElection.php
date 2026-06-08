<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Entities;

use App\Modules\Tax\Domain\Exceptions\OsdElectionMismatchException;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;
use DateTimeImmutable;
use DomainException;

/**
 * The locked-in deduction regime for one (company, fiscal year, taxpayer type)
 * combination. Once recorded, every subsequent ITR for the year must respect
 * the regime stored here.
 *
 * The only legal mutation is `supersede()`, which marks this row as replaced
 * by a successor record (used for genuine BIR-permitted amendments — e.g.
 * the taxpayer realises Q1 was filed under the wrong regime and asks BIR
 * for permission to refile).
 */
final class OsdElection
{
    public ?DateTimeImmutable $supersededAt = null;

    public ?string $supersedeReason = null;

    public function __construct(
        public readonly OsdElectionId $id,
        public readonly string $companyId,
        public readonly int $fiscalYear,
        public readonly string $taxpayerType,           // 'individual' | 'corporate'
        public readonly string $regime,                  // 'itemized' | 'osd' | 'flat_8pct'
        public readonly string $declaredInFormType,
        public readonly ?int $declaredInQuarter,        // null for annual-declared
        public readonly ?string $declaredInBirFormId,
        public readonly DateTimeImmutable $lockedAt,
        public readonly string $lockedBy,
        public readonly ?OsdElectionId $replacesId = null,
    ) {
        if (! in_array($taxpayerType, ['individual', 'corporate'], true)) {
            throw new DomainException("Invalid taxpayer_type '{$taxpayerType}'.");
        }
        if (! in_array($regime, ['itemized', 'osd', 'flat_8pct'], true)) {
            throw new DomainException("Invalid regime '{$regime}'.");
        }
        if ($regime === 'flat_8pct' && $taxpayerType !== 'individual') {
            throw new DomainException(
                "8% flat regime is restricted to individual taxpayers (RA 10963 / TRAIN)."
            );
        }
        if ($declaredInQuarter !== null && ($declaredInQuarter < 1 || $declaredInQuarter > 4)) {
            throw new DomainException("Invalid declared_in_quarter {$declaredInQuarter}.");
        }
    }

    /**
     * Confirms the supplied regime matches this locked election; throws if not.
     * Called by every quarterly + annual ITR generator before computing.
     */
    public function assertMatches(string $attemptedRegime): void
    {
        if ($attemptedRegime !== $this->regime) {
            throw OsdElectionMismatchException::on(
                fiscalYear:         $this->fiscalYear,
                lockedRegime:       $this->regime,
                attemptedRegime:    $attemptedRegime,
                declaredInFormType: $this->declaredInFormType,
            );
        }
    }

    /**
     * Marks this election as superseded by an amendment. Once superseded, the
     * row should be excluded from active-lookup queries (the repo enforces).
     */
    public function supersede(string $reason): void
    {
        if ($this->supersededAt !== null) {
            throw new DomainException("Election {$this->id} is already superseded.");
        }
        if (trim($reason) === '') {
            throw new DomainException('Supersede reason is required.');
        }

        $this->supersededAt    = new DateTimeImmutable();
        $this->supersedeReason = $reason;
    }

    public function isActive(): bool
    {
        return $this->supersededAt === null;
    }
}
