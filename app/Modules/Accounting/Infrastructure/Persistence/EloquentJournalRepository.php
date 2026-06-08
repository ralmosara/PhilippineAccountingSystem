<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence;

use App\Modules\Accounting\Application\Contracts\JournalRepositoryContract;
use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Domain\Entities\JournalLine;
use App\Modules\Accounting\Domain\ValueObjects\AccountId;
use App\Modules\Accounting\Domain\ValueObjects\JournalEntryId;
use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\DocumentSeriesModel;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\JournalEntryModel;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\JournalLineModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed JournalRepository.
 *
 * Translates between Domain entities (pure PHP) and Eloquent models
 * (framework-bound). The Application layer never sees Eloquent.
 */
final class EloquentJournalRepository implements JournalRepositoryContract
{
    public function findById(JournalEntryId $id): ?JournalEntry
    {
        $model = JournalEntryModel::query()->with('lines')->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(JournalEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            JournalEntryModel::query()->updateOrInsert(
                ['id' => $entry->id->value],
                [
                    'company_id'         => $entry->companyId,
                    'fiscal_period_id'   => $entry->fiscalPeriodId,
                    'document_series_id' => $entry->documentSeriesId,
                    'sequence_no'        => $this->extractSequenceNo($entry->docNo),
                    'doc_no'             => $entry->docNo,
                    'entry_date'         => $entry->entryDate,
                    'memo'               => $entry->memo,
                    'source'             => $entry->source,
                    'source_doc_id'      => $entry->sourceDocId,
                    'source_doc_type'    => $entry->sourceDocType,
                    'posted_at'          => $entry->postedAt,
                    'posted_by'          => $entry->postedBy,
                    'updated_at'         => now(),
                    'created_at'         => now(),
                ],
            );

            // Replace lines on save
            JournalLineModel::query()
                ->where('journal_entry_id', $entry->id->value)
                ->delete();

            foreach ($entry->lines as $line) {
                JournalLineModel::query()->create([
                    'id'               => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                    'journal_entry_id' => $entry->id->value,
                    'line_no'          => $line->lineNo,
                    'account_id'       => $line->accountId->value,
                    'currency'         => $line->debit->currency,
                    'debit'            => $line->debit->amount,
                    'credit'           => $line->credit->amount,
                    'fx_rate'          => $line->fxRate,
                    'php_amount'       => $line->phpAmount->amount,
                    'tax_code_id'      => $line->taxCodeId,
                    'cost_center_id'   => $line->costCenterId,
                    'project_id'       => $line->projectId,
                    'memo'             => $line->memo,
                ]);
            }
        });
    }

    public function allocateDocNo(string $documentSeriesId): string
    {
        $row = DB::selectOne(
            'SELECT accounting.allocate_doc_no(?::uuid) AS sequence_no',
            [$documentSeriesId],
        );
        $sequence = (int) $row->sequence_no;

        $series = DocumentSeriesModel::findOrFail($documentSeriesId);

        return $series->prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function linkReversal(JournalEntryId $original, JournalEntryId $reversal): void
    {
        // The DB trigger blocks generic UPDATEs on posted entries, but
        // permits this specific column change because reversed_by is
        // explicitly excluded from the trigger's protection list.
        JournalEntryModel::query()
            ->where('id', $original->value)
            ->update(['reversed_by' => $reversal->value]);
    }

    private function toDomain(JournalEntryModel $model): JournalEntry
    {
        $entry = new JournalEntry(
            id:               new JournalEntryId($model->id),
            companyId:        $model->company_id,
            fiscalPeriodId:   $model->fiscal_period_id,
            documentSeriesId: $model->document_series_id,
            docNo:            $model->doc_no,
            entryDate:        new DateTimeImmutable($model->entry_date->toIso8601String()),
            source:           $model->source,
            sourceDocId:      $model->source_doc_id,
            sourceDocType:    $model->source_doc_type,
            memo:             $model->memo,
        );

        if ($model->posted_at !== null) {
            $entry->postedAt = new DateTimeImmutable($model->posted_at->toIso8601String());
            $entry->postedBy = $model->posted_by;
        }

        foreach ($model->lines as $lineModel) {
            $entry->lines[] = new JournalLine(
                lineNo:       (int) $lineModel->line_no,
                accountId:    new AccountId($lineModel->account_id),
                debit:        new Money((string) $lineModel->debit, (string) $lineModel->currency),
                credit:       new Money((string) $lineModel->credit, (string) $lineModel->currency),
                phpAmount:    new Money((string) $lineModel->php_amount, 'PHP'),
                fxRate:       (string) $lineModel->fx_rate,
                taxCodeId:    $lineModel->tax_code_id,
                costCenterId: $lineModel->cost_center_id,
                projectId:    $lineModel->project_id,
                memo:         $lineModel->memo,
            );
        }

        return $entry;
    }

    private function extractSequenceNo(string $docNo): int
    {
        if (preg_match('/(\d+)$/', $docNo, $m)) {
            return (int) $m[1];
        }
        throw new \InvalidArgumentException("Could not extract sequence from doc_no: {$docNo}");
    }
}
