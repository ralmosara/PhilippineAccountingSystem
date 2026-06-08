<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Entities;

use App\Modules\Tax\Domain\Exceptions\Form2307AlreadyClaimedException;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;
use DateTimeImmutable;
use DomainException;

/**
 * A Certificate of Creditable Tax Withheld (BIR Form 2307) received from
 * one of our customers/clients. Income tax they remitted to BIR on our
 * behalf — credited against our 1701 / 1702 tax due at filing time.
 *
 * Mutation rules:
 *   - Status transitions are strictly forward: draft → recorded → claimed.
 *   - Once `claimed` (linked to a filed 1701/1702), the row is effectively
 *     immutable. Corrections require a new row with `rejected` status on
 *     the old one + a fresh recorded entry.
 *   - `rejected` is terminal and excluded from credit aggregation.
 */
final class Form2307Received
{
    public string $status;

    public ?string $claimedInBirFormId = null;

    public ?string $rejectionReason = null;

    public function __construct(
        public readonly Form2307ReceivedId $id,
        public readonly string $companyId,
        public readonly ?string $customerId,
        public readonly string $payorTin,
        public readonly string $payorRegisteredName,
        public readonly string $payorBranchCode,
        public readonly ?string $payorAddress,
        public readonly ?string $certificateNo,
        public readonly string $atcCode,
        public readonly DateTimeImmutable $periodFrom,
        public readonly DateTimeImmutable $periodTo,
        public readonly string $incomePayment,             // BCMath numeric string
        public readonly string $taxWithheld,               // BCMath numeric string
        public readonly ?string $sourcePdfPath,
        public readonly string $entryMethod,               // 'manual' | 'pdf_upload' | 'csv_import'
        public readonly ?string $journalEntryId,
        public readonly string $recordedBy,
        string $status = 'recorded',
    ) {
        if ($periodFrom > $periodTo) {
            throw new DomainException("2307 period invalid: periodFrom ({$periodFrom->format('Y-m-d')}) > periodTo.");
        }
        if (bccomp($incomePayment, '0', 2) < 0) {
            throw new DomainException('2307 incomePayment cannot be negative.');
        }
        if (bccomp($taxWithheld, '0', 2) < 0) {
            throw new DomainException('2307 taxWithheld cannot be negative.');
        }
        if (bccomp($taxWithheld, $incomePayment, 2) > 0) {
            throw new DomainException(
                "2307 taxWithheld ({$taxWithheld}) exceeds incomePayment ({$incomePayment}). "
                ."Did the payor file the wrong column?"
            );
        }

        $this->status = $status;
    }

    /**
     * Mark this certificate as claimed against a specific 1701/1702 BIR form.
     * Idempotent on re-claiming the same form; throws if a different form
     * already claimed it.
     */
    public function claim(string $birFormId): void
    {
        if ($this->status === 'claimed' && $this->claimedInBirFormId === $birFormId) {
            return;
        }
        if ($this->status === 'claimed') {
            throw new Form2307AlreadyClaimedException(
                "Cert {$this->id} already claimed in form {$this->claimedInBirFormId}; "
                ."cannot re-claim in {$birFormId}."
            );
        }
        if ($this->status === 'rejected') {
            throw new DomainException("Cannot claim a rejected 2307: {$this->id}.");
        }

        $this->status = 'claimed';
        $this->claimedInBirFormId = $birFormId;
    }

    /**
     * Reject this certificate (BIR auditor flagged it, or payor TIN mismatch,
     * or we received a corrected version). Excluded from future aggregation.
     */
    public function reject(string $reason): void
    {
        if ($this->status === 'claimed') {
            throw new DomainException(
                "Cannot reject 2307 {$this->id} — already claimed in {$this->claimedInBirFormId}. "
                ."Amend the 1701/1702 first."
            );
        }
        if (trim($reason) === '') {
            throw new DomainException('Rejection reason is required.');
        }

        $this->status = 'rejected';
        $this->rejectionReason = $reason;
    }

    public function isClaimable(): bool
    {
        return $this->status === 'recorded';
    }

    /** Quarter (1..4) the period_to falls in (Philippine calendar fiscal year default). */
    public function quarter(): int
    {
        return (int) ceil(((int) $this->periodTo->format('n')) / 3);
    }

    /** Fiscal year — calendar-year default. */
    public function fiscalYear(): int
    {
        return (int) $this->periodTo->format('Y');
    }
}
