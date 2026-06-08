<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Contracts;

use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Domain\ValueObjects\JournalEntryId;

interface JournalRepositoryContract
{
    public function findById(JournalEntryId $id): ?JournalEntry;

    public function save(JournalEntry $entry): void;

    /**
     * Allocates the next sequence number from the document_series and
     * returns the formatted doc_no (e.g. 'JV-2026-000001'). Atomic via
     * accounting.allocate_doc_no() Postgres function.
     */
    public function allocateDocNo(string $documentSeriesId): string;

    /**
     * Records the reversed_by linkage on the original posted entry.
     * This is the only mutation allowed on a posted journal entry.
     */
    public function linkReversal(JournalEntryId $original, JournalEntryId $reversal): void;
}
