<?php

declare(strict_types=1);

namespace App\Modules\Projects\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Projects\Application\Contracts\TimesheetRepositoryContract;
use App\Modules\Projects\Domain\Entities\TimesheetEntry;
use App\Modules\Projects\Infrastructure\Persistence\Eloquent\TimesheetEntryModel;
use DateTimeImmutable;

final class EloquentTimesheetRepository implements TimesheetRepositoryContract
{
    public function save(TimesheetEntry $entry): void
    {
        TimesheetEntryModel::query()->updateOrInsert(
            ['id' => $entry->id],
            [
                'project_id'      => $entry->projectId,
                'employee_id'     => $entry->employeeId,
                'work_date'       => $entry->workDate->format('Y-m-d'),
                'hours'           => $entry->hours,
                'billable_rate'   => $entry->billableRate->toPhp(),
                'billable_amount' => $entry->billableAmount->toPhp(),
                'description'     => $entry->description,
                'is_billed'       => $entry->isBilled,
                'updated_at'      => now(),
                'created_at'      => now(),
            ],
        );
    }

    /** @return list<TimesheetEntry> */
    public function listForProject(string $projectId): array
    {
        return TimesheetEntryModel::query()
            ->where('project_id', $projectId)
            ->orderByDesc('work_date')
            ->get()
            ->map(fn (TimesheetEntryModel $m) => $this->toDomain($m))
            ->values()
            ->all();
    }

    public function sumUnbilledHoursForProject(
        string $projectId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): string {
        $sum = TimesheetEntryModel::query()
            ->where('project_id', $projectId)
            ->where('is_billed', false)
            ->whereBetween('work_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->sum('hours');

        return bcadd((string) $sum, '0', 2);
    }

    /** @return list<TimesheetEntry> */
    public function listUnbilledForProject(
        string $projectId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        return TimesheetEntryModel::query()
            ->where('project_id', $projectId)
            ->where('is_billed', false)
            ->whereBetween('work_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('work_date')
            ->get()
            ->map(fn (TimesheetEntryModel $m) => $this->toDomain($m))
            ->values()
            ->all();
    }

    public function markBilled(string ...$ids): void
    {
        TimesheetEntryModel::query()
            ->whereIn('id', $ids)
            ->update(['is_billed' => true, 'updated_at' => now()]);
    }

    private function toDomain(TimesheetEntryModel $m): TimesheetEntry
    {
        return new TimesheetEntry(
            id:             $m->id,
            projectId:      $m->project_id,
            employeeId:     $m->employee_id,
            workDate:       new DateTimeImmutable($m->work_date->toDateString()),
            hours:          (string) $m->hours,
            billableRate:   Money::php((string) $m->billable_rate),
            billableAmount: Money::php((string) $m->billable_amount),
            description:    $m->description,
            isBilled:       (bool) $m->is_billed,
        );
    }
}
