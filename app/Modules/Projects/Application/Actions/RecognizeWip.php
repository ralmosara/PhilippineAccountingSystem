<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Projects\Application\Contracts\ProjectRepositoryContract;
use App\Modules\Projects\Application\Contracts\TimesheetRepositoryContract;
use App\Modules\Projects\Application\Contracts\WipRepositoryContract;
use App\Modules\Projects\Application\Exceptions\ProjectNotFoundException;
use App\Modules\Projects\Domain\Entities\WipEntry;
use App\Modules\Projects\Domain\Events\WipRecognized;
use App\Modules\Projects\Domain\ValueObjects\ProjectId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class RecognizeWip
{
    public function __construct(
        private ProjectRepositoryContract $projects,
        private TimesheetRepositoryContract $timesheets,
        private WipRepositoryContract $wipEntries,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    /**
     * @param  array{
     *     period_from: string,
     *     period_to: string,
     *     wip_account_id: string,
     *     revenue_account_id: string,
     * } $data
     */
    public function execute(
        string $projectId,
        string $companyId,
        array $data,
        string $actorId,
    ): WipEntry {
        return DB::transaction(function () use ($projectId, $companyId, $data, $actorId) {
            $project = $this->projects->findById(new ProjectId($projectId))
                ?? throw new ProjectNotFoundException($projectId);

            $from = new DateTimeImmutable($data['period_from']);
            $to   = new DateTimeImmutable($data['period_to']);

            // Aggregate unbilled timesheets for the period
            $unbilled       = $this->timesheets->listUnbilledForProject($projectId, $from, $to);
            $totalHours     = '0';
            $totalBillable  = '0.00';

            foreach ($unbilled as $entry) {
                $totalHours    = bcadd($totalHours, $entry->hours, 2);
                $totalBillable = bcadd($totalBillable, $entry->billableAmount->toPhp(), 2);
            }

            $recognizedRevenue = Money::php($totalBillable);

            // Build WIP entry
            $wipEntry = new WipEntry(
                id:                 Uuid::uuid4()->toString(),
                projectId:          $project->id->value,
                periodFrom:         $from,
                periodTo:           $to,
                totalHours:         $totalHours,
                totalCost:          Money::php($totalBillable),
                totalBilled:        $recognizedRevenue,
                recognizedRevenue:  $recognizedRevenue,
            );

            // Post journal entry for WIP recognition via direct DB insert
            // DR WIP Asset account, CR Revenue account
            $journalEntryId = Uuid::uuid4()->toString();
            $nowTz          = now()->toIso8601String();

            DB::table('accounting.journal_entries')->insert([
                'id'             => $journalEntryId,
                'company_id'     => $companyId,
                'entry_date'     => $from->format('Y-m-d'),
                'memo'           => "WIP recognition: project {$project->code} {$data['period_from']} – {$data['period_to']}",
                'source'         => 'projects',
                'source_doc_id'  => $wipEntry->id,
                'source_doc_type'=> 'WipEntry',
                'posted_at'      => $nowTz,
                'posted_by'      => $actorId,
                'created_at'     => $nowTz,
                'updated_at'     => $nowTz,
            ]);

            if (bccomp($totalBillable, '0', 2) > 0) {
                // DR WIP Asset
                DB::table('accounting.journal_lines')->insert([
                    'id'               => Uuid::uuid4()->toString(),
                    'journal_entry_id' => $journalEntryId,
                    'account_id'       => $data['wip_account_id'],
                    'line_no'          => 1,
                    'debit'            => $totalBillable,
                    'credit'           => '0.00',
                    'php_amount'       => $totalBillable,
                    'memo'             => 'WIP asset accrual',
                    'created_at'       => $nowTz,
                    'updated_at'       => $nowTz,
                ]);

                // CR Revenue
                DB::table('accounting.journal_lines')->insert([
                    'id'               => Uuid::uuid4()->toString(),
                    'journal_entry_id' => $journalEntryId,
                    'account_id'       => $data['revenue_account_id'],
                    'line_no'          => 2,
                    'debit'            => '0.00',
                    'credit'           => $totalBillable,
                    'php_amount'       => bcmul($totalBillable, '-1', 2),
                    'memo'             => 'Project revenue recognition',
                    'created_at'       => $nowTz,
                    'updated_at'       => $nowTz,
                ]);
            }

            $wipEntry->journalEntryId = $journalEntryId;
            $wipEntry->post($actorId);
            $this->wipEntries->save($wipEntry);

            // Mark timesheets as billed
            if (count($unbilled) > 0) {
                $ids = array_map(fn ($e) => $e->id, $unbilled);
                $this->timesheets->markBilled(...$ids);
            }

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'wip.recognized',
                aggregate:   'WipEntry',
                aggregateId: $wipEntry->id,
                payload: [
                    'project_id'         => $project->id->value,
                    'project_code'       => $project->code,
                    'period_from'        => $data['period_from'],
                    'period_to'          => $data['period_to'],
                    'total_hours'        => $totalHours,
                    'recognized_revenue' => $totalBillable,
                    'journal_entry_id'   => $journalEntryId,
                ],
            );

            $this->events->dispatch(new WipRecognized(
                wipEntryId:        $wipEntry->id,
                projectId:         $project->id->value,
                companyId:         $companyId,
                periodFrom:        $data['period_from'],
                periodTo:          $data['period_to'],
                totalHours:        $totalHours,
                recognizedRevenue: $totalBillable,
                journalEntryId:    $journalEntryId,
                postedAt:          $wipEntry->postedAt,
                postedBy:          $actorId,
            ));

            return $wipEntry;
        });
    }
}
