<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Application\Contracts\AccountRepositoryContract;
use App\Modules\Accounting\Application\Contracts\FiscalPeriodRepositoryContract;
use App\Modules\Accounting\Application\Contracts\JournalRepositoryContract;
use App\Modules\Accounting\Application\Exceptions\AccountNotPostableException;
use App\Modules\Accounting\Application\Exceptions\FiscalPeriodLockedException;
use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Domain\Entities\JournalLine;
use App\Modules\Accounting\Domain\Services\DoubleEntryValidator;
use App\Modules\Accounting\Domain\ValueObjects\AccountId;
use App\Modules\Accounting\Domain\ValueObjects\JournalEntryId;
use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft journal entry. Draft entries are unbalanced-tolerant
 * (the deferred constraint only fires after posting); they may be edited
 * freely until posted.
 */
final readonly class CreateJournalEntry
{
    public function __construct(
        private JournalRepositoryContract $journals,
        private AccountRepositoryContract $accounts,
        private FiscalPeriodRepositoryContract $periods,
        private DoubleEntryValidator $validator,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @param  array<int, array{
     *     account_id: string,
     *     debit?: string|float|int,
     *     credit?: string|float|int,
     *     fx_rate?: string,
     *     php_amount?: string,
     *     tax_code_id?: string|null,
     *     cost_center_id?: string|null,
     *     project_id?: string|null,
     *     memo?: string|null,
     * }>  $lines
     */
    public function execute(
        string $companyId,
        string $documentSeriesId,
        DateTimeImmutable $entryDate,
        array $lines,
        string $source = 'manual',
        ?string $sourceDocId = null,
        ?string $sourceDocType = null,
        ?string $memo = null,
        ?string $actorId = null,
    ): JournalEntry {
        $fiscalPeriodId = $this->periods->findContaining($companyId, $entryDate);

        if ($fiscalPeriodId === null) {
            throw new \InvalidArgumentException(
                'Entry date '.$entryDate->format('Y-m-d').' is not within any defined fiscal period.'
            );
        }

        if ($this->periods->isLocked($fiscalPeriodId)) {
            throw new FiscalPeriodLockedException($fiscalPeriodId);
        }

        return DB::transaction(function () use (
            $companyId, $documentSeriesId, $fiscalPeriodId, $entryDate,
            $lines, $source, $sourceDocId, $sourceDocType, $memo, $actorId
        ) {
            $docNo = $this->journals->allocateDocNo($documentSeriesId);

            $entry = new JournalEntry(
                id:               JournalEntryId::generate(),
                companyId:        $companyId,
                fiscalPeriodId:   $fiscalPeriodId,
                documentSeriesId: $documentSeriesId,
                docNo:            $docNo,
                entryDate:        $entryDate,
                source:           $source,
                sourceDocId:      $sourceDocId,
                sourceDocType:    $sourceDocType,
                memo:             $memo,
            );

            foreach ($lines as $i => $row) {
                $accountId = new AccountId($row['account_id']);

                if (! $this->accounts->isPostable($accountId)) {
                    throw new AccountNotPostableException($accountId->value);
                }

                $debit  = Money::php((string) ($row['debit']  ?? '0'));
                $credit = Money::php((string) ($row['credit'] ?? '0'));

                // php_amount: signed, defaults to debit-positive / credit-negative
                $php = $row['php_amount'] ?? ($debit->isPositive() ? $debit->amount : $credit->negate()->amount);

                $entry->addLine(new JournalLine(
                    lineNo:       $i + 1,
                    accountId:    $accountId,
                    debit:        $debit,
                    credit:       $credit,
                    phpAmount:    new Money($php, 'PHP'),
                    fxRate:       (string) ($row['fx_rate'] ?? '1'),
                    taxCodeId:    $row['tax_code_id']    ?? null,
                    costCenterId: $row['cost_center_id'] ?? null,
                    projectId:    $row['project_id']     ?? null,
                    memo:         $row['memo']           ?? null,
                ));
            }

            $this->journals->save($entry);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'journalentry.drafted',
                aggregate:   'JournalEntry',
                aggregateId: $entry->id->value,
                payload: [
                    'doc_no'      => $entry->docNo,
                    'entry_date'  => $entry->entryDate->format('Y-m-d'),
                    'source'      => $entry->source,
                    'lines_count' => count($entry->lines),
                    'total'       => $entry->totalDebits()->toPhp(),
                ],
            );

            return $entry;
        });
    }
}
