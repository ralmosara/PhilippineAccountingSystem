<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Projects\Application\Contracts\ProjectRepositoryContract;
use App\Modules\Projects\Application\Contracts\TimesheetRepositoryContract;
use App\Modules\Projects\Application\Exceptions\ProjectNotFoundException;
use App\Modules\Projects\Domain\Entities\TimesheetEntry;
use App\Modules\Projects\Domain\ValueObjects\ProjectId;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class LogTimesheetEntry
{
    public function __construct(
        private ProjectRepositoryContract $projects,
        private TimesheetRepositoryContract $timesheets,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @param  array{
     *     employee_id: string,
     *     work_date: string,
     *     hours: string,
     *     billable_rate?: string|null,
     *     description?: string|null,
     * } $data
     */
    public function execute(
        string $projectId,
        string $companyId,
        array $data,
        string $actorId,
    ): TimesheetEntry {
        return DB::transaction(function () use ($projectId, $companyId, $data, $actorId) {
            $project = $this->projects->findById(new ProjectId($projectId))
                ?? throw new ProjectNotFoundException($projectId);

            $hours = (string) $data['hours'];

            if (bccomp($hours, '0', 2) <= 0) {
                throw new DomainException('Hours must be greater than zero.');
            }
            if (bccomp($hours, '24', 2) > 0) {
                throw new DomainException('Hours cannot exceed 24 per entry.');
            }

            $billableRate   = Money::php((string) ($data['billable_rate'] ?? '0'));
            $billableAmount = Money::php(bcmul($hours, $billableRate->toPhp(), 2));

            $entry = new TimesheetEntry(
                id:             Uuid::uuid4()->toString(),
                projectId:      $project->id->value,
                employeeId:     $data['employee_id'],
                workDate:       new DateTimeImmutable($data['work_date']),
                hours:          $hours,
                billableRate:   $billableRate,
                billableAmount: $billableAmount,
                description:    $data['description'] ?? null,
                isBilled:       false,
            );

            $this->timesheets->save($entry);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'timesheet.logged',
                aggregate:   'TimesheetEntry',
                aggregateId: $entry->id,
                payload: [
                    'project_id'      => $entry->projectId,
                    'employee_id'     => $entry->employeeId,
                    'work_date'       => $entry->workDate->format('Y-m-d'),
                    'hours'           => $entry->hours,
                    'billable_amount' => $entry->billableAmount->toPhp(),
                ],
            );

            return $entry;
        });
    }
}
