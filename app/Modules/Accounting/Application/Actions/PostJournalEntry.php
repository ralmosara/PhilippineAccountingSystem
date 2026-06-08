<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Application\Contracts\FiscalPeriodRepositoryContract;
use App\Modules\Accounting\Application\Contracts\JournalRepositoryContract;
use App\Modules\Accounting\Application\Exceptions\FiscalPeriodLockedException;
use App\Modules\Accounting\Application\Exceptions\JournalNotFoundException;
use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Domain\Events\JournalPosted;
use App\Modules\Accounting\Domain\Services\DoubleEntryValidator;
use App\Modules\Accounting\Domain\ValueObjects\JournalEntryId;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Posts a draft journal entry. Once posted:
 *   - Lines are immutable (DB-level trigger blocks further edits)
 *   - The deferred balance constraint fires and confirms debits = credits
 *   - JournalPosted domain event is dispatched (Reporting/Tax may listen)
 *   - Audit event is written via the hash chain
 *
 * This is the canonical example of a single-action invokable workflow:
 *   POST /journals/{id}/post  →  PostJournalEntryController::__invoke
 *                            →  PostJournalEntry::execute  (this class)
 */
final readonly class PostJournalEntry
{
    public function __construct(
        private JournalRepositoryContract $journals,
        private FiscalPeriodRepositoryContract $periods,
        private DoubleEntryValidator $validator,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $journalEntryId, string $actorId): JournalEntry
    {
        $id = new JournalEntryId($journalEntryId);

        $entry = $this->journals->findById($id)
            ?? throw new JournalNotFoundException($journalEntryId);

        if ($this->periods->isLocked($entry->fiscalPeriodId)) {
            throw new FiscalPeriodLockedException($entry->fiscalPeriodId);
        }

        $this->validator->validate($entry);

        return DB::transaction(function () use ($entry, $actorId) {
            $entry->post(postedBy: $actorId);

            $this->journals->save($entry);

            // Audit before dispatching events so listeners observe a consistent state
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $entry->companyId,
                eventType:   'journalentry.posted',
                aggregate:   'JournalEntry',
                aggregateId: $entry->id->value,
                payload: [
                    'doc_no'        => $entry->docNo,
                    'entry_date'    => $entry->entryDate->format('Y-m-d'),
                    'total_debits'  => $entry->totalDebits()->toPhp(),
                    'total_credits' => $entry->totalCredits()->toPhp(),
                    'lines_count'   => count($entry->lines),
                    'source'        => $entry->source,
                    'source_doc'    => $entry->sourceDocId,
                ],
            );

            $this->events->dispatch(new JournalPosted(
                journalEntryId: $entry->id->value,
                companyId:      $entry->companyId,
                docNo:          $entry->docNo,
                fiscalPeriodId: $entry->fiscalPeriodId,
                postedAt:       $entry->postedAt,
                postedBy:       $actorId,
                source:         $entry->source,
            ));

            return $entry;
        });
    }
}
