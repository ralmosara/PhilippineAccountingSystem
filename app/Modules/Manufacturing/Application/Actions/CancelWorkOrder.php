<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Manufacturing\Application\Contracts\WorkOrderRepositoryContract;
use App\Modules\Manufacturing\Domain\Entities\WorkOrder;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderId;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CancelWorkOrder
{
    public function __construct(
        private WorkOrderRepositoryContract $workOrders,
        private AuditWriterContract         $audit,
    ) {
    }

    public function execute(string $workOrderId, string $actorId): WorkOrder
    {
        $workOrder = $this->workOrders->findById(new WorkOrderId($workOrderId));

        if ($workOrder === null) {
            throw new InvalidArgumentException("Work order {$workOrderId} not found.");
        }

        return DB::transaction(function () use ($workOrder, $actorId) {
            $workOrder->cancel();

            $this->workOrders->save($workOrder);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $workOrder->companyId,
                eventType:   'workorder.cancelled',
                aggregate:   'WorkOrder',
                aggregateId: $workOrder->id->value,
                payload:     [
                    'work_order_no' => $workOrder->workOrderNo,
                ],
            );

            return $workOrder;
        });
    }
}
