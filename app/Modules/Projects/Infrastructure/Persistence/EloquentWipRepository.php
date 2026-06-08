<?php

declare(strict_types=1);

namespace App\Modules\Projects\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Projects\Application\Contracts\WipRepositoryContract;
use App\Modules\Projects\Domain\Entities\WipEntry;
use App\Modules\Projects\Infrastructure\Persistence\Eloquent\WipEntryModel;
use DateTimeImmutable;

final class EloquentWipRepository implements WipRepositoryContract
{
    public function save(WipEntry $entry): void
    {
        WipEntryModel::query()->updateOrInsert(
            ['id' => $entry->id],
            [
                'project_id'         => $entry->projectId,
                'journal_entry_id'   => $entry->journalEntryId,
                'period_from'        => $entry->periodFrom->format('Y-m-d'),
                'period_to'          => $entry->periodTo->format('Y-m-d'),
                'total_hours'        => $entry->totalHours,
                'total_cost'         => $entry->totalCost->toPhp(),
                'total_billed'       => $entry->totalBilled->toPhp(),
                'recognized_revenue' => $entry->recognizedRevenue->toPhp(),
                'status'             => $entry->status,
                'posted_at'          => $entry->postedAt?->format('Y-m-d\TH:i:sP'),
                'posted_by'          => $entry->postedBy,
                'updated_at'         => now(),
                'created_at'         => now(),
            ],
        );
    }

    /** @return list<WipEntry> */
    public function listForProject(string $projectId): array
    {
        return WipEntryModel::query()
            ->where('project_id', $projectId)
            ->orderByDesc('period_from')
            ->get()
            ->map(fn (WipEntryModel $m) => $this->toDomain($m))
            ->values()
            ->all();
    }

    private function toDomain(WipEntryModel $m): WipEntry
    {
        $entry = new WipEntry(
            id:                $m->id,
            projectId:         $m->project_id,
            periodFrom:        new DateTimeImmutable($m->period_from->toDateString()),
            periodTo:          new DateTimeImmutable($m->period_to->toDateString()),
            totalHours:        (string) $m->total_hours,
            totalCost:         Money::php((string) $m->total_cost),
            totalBilled:       Money::php((string) $m->total_billed),
            recognizedRevenue: Money::php((string) $m->recognized_revenue),
            journalEntryId:    $m->journal_entry_id,
        );

        $entry->status   = $m->status;
        $entry->postedAt = $m->posted_at ? new DateTimeImmutable($m->posted_at->toIso8601String()) : null;
        $entry->postedBy = $m->posted_by;

        return $entry;
    }
}
