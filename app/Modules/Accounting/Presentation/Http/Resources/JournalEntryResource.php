<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Resources;

use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\JournalEntryModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Accepts either a Domain JournalEntry (returned by Actions) or an Eloquent
 * JournalEntryModel (returned by index/show queries). Shapes the response
 * uniformly so the SPA only sees one schema.
 */
final class JournalEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof JournalEntry) {
            return $this->fromDomain($this->resource);
        }

        return $this->fromModel($this->resource);
    }

    /** @return array<string, mixed> */
    private function fromDomain(JournalEntry $entry): array
    {
        return [
            'id'                 => $entry->id->value,
            'doc_no'             => $entry->docNo,
            'company_id'         => $entry->companyId,
            'fiscal_period_id'   => $entry->fiscalPeriodId,
            'document_series_id' => $entry->documentSeriesId,
            'entry_date'         => $entry->entryDate->format('Y-m-d'),
            'memo'               => $entry->memo,
            'source'             => $entry->source,
            'source_doc_id'      => $entry->sourceDocId,
            'source_doc_type'    => $entry->sourceDocType,
            'posted_at'          => $entry->postedAt?->format(\DateTimeInterface::ATOM),
            'posted_by'          => $entry->postedBy,
            'is_posted'          => $entry->postedAt !== null,
            'totals'             => [
                'debit'    => $entry->totalDebits()->toPhp(),
                'credit'   => $entry->totalCredits()->toPhp(),
                'balanced' => $entry->isBalanced(),
            ],
            'lines' => array_map(fn ($l) => [
                'line_no'        => $l->lineNo,
                'account_id'     => $l->accountId->value,
                'currency'       => $l->debit->currency,
                'debit'          => $l->debit->amount,
                'credit'         => $l->credit->amount,
                'fx_rate'        => $l->fxRate,
                'php_amount'     => $l->phpAmount->amount,
                'tax_code_id'    => $l->taxCodeId,
                'cost_center_id' => $l->costCenterId,
                'project_id'     => $l->projectId,
                'memo'           => $l->memo,
            ], $entry->lines),
        ];
    }

    /** @return array<string, mixed> */
    private function fromModel(JournalEntryModel $model): array
    {
        return [
            'id'                 => $model->id,
            'doc_no'             => $model->doc_no,
            'company_id'         => $model->company_id,
            'fiscal_period_id'   => $model->fiscal_period_id,
            'document_series_id' => $model->document_series_id,
            'entry_date'         => $model->entry_date?->format('Y-m-d'),
            'memo'               => $model->memo,
            'source'             => $model->source,
            'source_doc_id'      => $model->source_doc_id,
            'source_doc_type'    => $model->source_doc_type,
            'posted_at'          => $model->posted_at?->toIso8601String(),
            'posted_by'          => $model->posted_by,
            'reversed_by'        => $model->reversed_by,
            'is_posted'          => $model->posted_at !== null,
            'lines' => $this->whenLoaded('lines', fn () => $model->lines->map(fn ($l) => [
                'line_no'        => (int) $l->line_no,
                'account_id'     => $l->account_id,
                'currency'       => $l->currency,
                'debit'          => (string) $l->debit,
                'credit'         => (string) $l->credit,
                'fx_rate'        => (string) $l->fx_rate,
                'php_amount'     => (string) $l->php_amount,
                'tax_code_id'    => $l->tax_code_id,
                'cost_center_id' => $l->cost_center_id,
                'project_id'     => $l->project_id,
                'memo'           => $l->memo,
            ])),
        ];
    }
}
