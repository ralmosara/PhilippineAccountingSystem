<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Application\Contracts\FiscalPeriodRepositoryContract;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use Illuminate\Support\Facades\DB;

final readonly class LockFiscalPeriod
{
    public function __construct(
        private FiscalPeriodRepositoryContract $periods,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(
        string $fiscalPeriodId,
        string $companyId,
        string $actorId,
        ?string $reason = null,
    ): void {
        DB::transaction(function () use ($fiscalPeriodId, $companyId, $actorId, $reason) {
            $this->periods->lock($fiscalPeriodId, $actorId, $reason);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'fiscalperiod.locked',
                aggregate:   'FiscalPeriod',
                aggregateId: $fiscalPeriodId,
                payload:     ['reason' => $reason],
            );
        });
    }
}
