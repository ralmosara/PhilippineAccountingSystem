<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Application\Contracts\FiscalPeriodRepositoryContract;
use App\Modules\Accounting\Application\Contracts\JournalRepositoryContract;
use App\Modules\Accounting\Application\Exceptions\FiscalPeriodLockedException;
use App\Modules\Accounting\Application\Exceptions\JournalNotFoundException;
use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Domain\Entities\JournalLine;
use App\Modules\Accounting\Domain\ValueObjects\JournalEntryId;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a posted journal entry by creating an opposite-sign entry
 * (debits become credits and vice versa) and linking the two via
 * journal_entries.reversed_by.
 *
 * BIR-compliant: the original entry is never deleted or modified — the
 * reversal is a separate, auditable transaction.
 */
final readonly class ReverseJournalEntry
{
    public function __construct(
        private JournalRepositoryContract $journals,
        private FiscalPeriodRepositoryContract $periods,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(
        string $originalJournalEntryId,
        DateTimeImmutable $reversalDate,
        string $reason,
        string $actorId,
    ): JournalEntry {
        $original = $this->journals->findById(new JournalEntryId($originalJournalEntryId))
            ?? throw new JournalNotFoundException($originalJournalEntryId);

        if ($original->postedAt === null) {
            throw new DomainException('Cannot reverse a draft journal entry; delete the draft instead.');
        }

        $reversalPeriod = $this->periods->findContaining($original->companyId, $reversalDate);
        if ($reversalPeriod === null) {
            throw new \InvalidArgumentException(
                'Reversal date '.$reversalDate->format('Y-m-d').' is not within any defined fiscal period.'
            );
        }

        if ($this->periods->isLocked($reversalPeriod)) {
            throw new FiscalPeriodLockedException($reversalPeriod);
        }

        return DB::transaction(function () use ($original, $reversalDate, $reversalPeriod, $reason, $actorId) {
            $docNo = $this->journals->allocateDocNo($original->documentSeriesId);

            $reversal = new JournalEntry(
                id:               JournalEntryId::generate(),
                companyId:        $original->companyId,
                fiscalPeriodId:   $reversalPeriod,
                documentSeriesId: $original->documentSeriesId,
                docNo:            $docNo,
                entryDate:        $reversalDate,
                source:           'reversal',
                sourceDocId:      $original->id->value,
                sourceDocType:    'JournalEntry',
                memo:             "Reversal of {$original->docNo}: {$reason}",
            );

            // Flip every line's debit/credit
            foreach ($original->lines as $line) {
                $reversal->addLine(new JournalLine(
                    lineNo:       $line->lineNo,
                    accountId:    $line->accountId,
                    debit:        $line->credit,           // was credit → now debit
                    credit:       $line->debit,            // was debit → now credit
                    phpAmount:    $line->phpAmount->negate(),
                    fxRate:       $line->fxRate,
                    taxCodeId:    $line->taxCodeId,
                    costCenterId: $line->costCenterId,
                    projectId:    $line->projectId,
                    memo:         $line->memo,
                ));
            }

            $reversal->post(postedBy: $actorId);

            $this->journals->save($reversal);
            $this->journals->linkReversal($original->id, $reversal->id);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $original->companyId,
                eventType:   'journalentry.reversed',
                aggregate:   'JournalEntry',
                aggregateId: $original->id->value,
                payload: [
                    'reversal_id'     => $reversal->id->value,
                    'reversal_doc_no' => $reversal->docNo,
                    'reason'          => $reason,
                ],
            );

            return $reversal;
        });
    }
}
