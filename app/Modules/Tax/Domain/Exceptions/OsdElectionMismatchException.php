<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Exceptions;

use DomainException;

/**
 * Raised when a follow-up quarterly / annual ITR is generated with a
 * deduction regime that contradicts the year's locked-in election.
 *
 *   Example: Q1 2026 was filed with regime='itemized'. The Q2 generator
 *   is called with use_osd=true. The action consults tax.osd_elections,
 *   finds the lock, and throws this exception before any DB writes.
 */
final class OsdElectionMismatchException extends DomainException
{
    public static function on(
        int $fiscalYear,
        string $lockedRegime,
        string $attemptedRegime,
        string $declaredInFormType,
    ): self {
        return new self(sprintf(
            "Deduction regime '%s' contradicts %s's locked election '%s' for fiscal year %d. "
                .'RR 2-2010 § 7 / RR 8-2018 § 4 forbid switching mid-year. '
                .'File an amendment of the earlier filing if a correction is genuinely warranted.',
            $attemptedRegime,
            $declaredInFormType,
            $lockedRegime,
            $fiscalYear,
        ));
    }
}
