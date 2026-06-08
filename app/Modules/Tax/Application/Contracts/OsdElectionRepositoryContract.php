<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Domain\Entities\OsdElection;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;

interface OsdElectionRepositoryContract
{
    public function findById(OsdElectionId $id): ?OsdElection;

    /**
     * Returns the ACTIVE (non-superseded) election for the year, or null.
     * Quarterly/annual ITR actions call this before persisting the form.
     */
    public function findActive(
        string $companyId,
        int $fiscalYear,
        string $taxpayerType,
    ): ?OsdElection;

    public function save(OsdElection $election): void;
}
