<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Domain\Entities\Form2307Received;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;
use DateTimeImmutable;

interface Form2307ReceivedRepositoryContract
{
    public function findById(Form2307ReceivedId $id): ?Form2307Received;

    public function save(Form2307Received $cert): void;

    public function delete(Form2307ReceivedId $id): void;

    /**
     * Returns claimable certs (status='recorded') whose period_to falls
     * within [from, to]. Used by ImportForm2307Received conflict-detection
     * and by AggregateCreditsForPeriod for 1701/1702 generation.
     *
     * @return list<Form2307Received>
     */
    public function findRecordedInPeriod(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array;

    /**
     * Atomic batch-claim — flips every supplied cert from 'recorded' to
     * 'claimed' against the given BIR form id. Used when a 1701/1702 is
     * generated to lock those certificates against double-counting.
     *
     * @param  list<Form2307ReceivedId>  $ids
     */
    public function markClaimed(array $ids, string $birFormId): void;

    /**
     * Hot-path query for duplicate detection during import. Returns true if
     * a non-rejected cert with the same payor + period + ATC + certNo exists.
     */
    public function existsDuplicate(
        string $companyId,
        string $payorTin,
        DateTimeImmutable $periodFrom,
        DateTimeImmutable $periodTo,
        string $atcCode,
        ?string $certificateNo,
    ): bool;
}
