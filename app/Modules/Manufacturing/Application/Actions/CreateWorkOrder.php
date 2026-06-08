<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Manufacturing\Application\Contracts\BomRepositoryContract;
use App\Modules\Manufacturing\Application\Contracts\WorkOrderRepositoryContract;
use App\Modules\Manufacturing\Domain\Entities\ProductionRunLine;
use App\Modules\Manufacturing\Domain\Entities\WorkOrder;
use App\Modules\Manufacturing\Domain\ValueObjects\BomId;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderId;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class CreateWorkOrder
{
    public function __construct(
        private BomRepositoryContract       $boms,
        private WorkOrderRepositoryContract $workOrders,
        private AuditWriterContract         $audit,
    ) {
    }

    public function execute(
        string  $companyId,
        string  $bomId,
        string  $quantityToProduce,
        ?string $scheduledStart = null,
        ?string $scheduledEnd = null,
        ?string $warehouseId = null,
        ?string $wipAccountId = null,
        ?string $finishedGoodsAccountId = null,
        ?string $rawMaterialsAccountId = null,
        ?string $actorId = null,
    ): WorkOrder {
        $bom = $this->boms->findById(new BomId($bomId));

        if ($bom === null) {
            throw new InvalidArgumentException("BOM {$bomId} not found.");
        }

        return DB::transaction(function () use (
            $companyId, $bom, $quantityToProduce,
            $scheduledStart, $scheduledEnd,
            $warehouseId, $wipAccountId, $finishedGoodsAccountId, $rawMaterialsAccountId,
            $actorId
        ) {
            $workOrderNo = $this->workOrders->nextWorkOrderNo($companyId);

            $workOrder = new WorkOrder(
                id:                     WorkOrderId::generate(),
                companyId:              $companyId,
                bomId:                  $bom->id,
                workOrderNo:            $workOrderNo,
                quantityToProduce:      bcadd($quantityToProduce, '0', 4),
                quantityProduced:       '0.0000',
                status:                 WorkOrderStatus::Draft,
                scheduledStart:         $scheduledStart,
                scheduledEnd:           $scheduledEnd,
                actualStart:            null,
                actualEnd:              null,
                warehouseId:            $warehouseId,
                wipAccountId:           $wipAccountId,
                finishedGoodsAccountId: $finishedGoodsAccountId,
                rawMaterialsAccountId:  $rawMaterialsAccountId,
                totalMaterialCost:      '0.00',
                totalLaborCost:         '0.00',
                totalOverheadCost:      '0.00',
                totalProductionCost:    '0.00',
                journalEntryId:         null,
            );

            // Expand BOM lines into production run lines
            // quantity_required = bom_line.quantity_per_batch × (quantity_to_produce / standard_batch_size)
            $batchRatio = bcdiv($quantityToProduce, $bom->standardBatchSize, 6);

            foreach ($bom->lines as $bomLine) {
                $qtyRequired = bcmul($bomLine->quantityPerBatch, $batchRatio, 4);

                $workOrder->lines[] = new ProductionRunLine(
                    id:               Uuid::uuid4()->toString(),
                    workOrderId:      $workOrder->id,
                    componentItemId:  $bomLine->componentItemId,
                    componentName:    $bomLine->componentName,
                    quantityRequired: $qtyRequired,
                    quantityConsumed: '0.0000',
                    unitCost:         Money::zero(),
                    totalCost:        Money::zero(),
                );
            }

            $this->workOrders->save($workOrder);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'workorder.created',
                aggregate:   'WorkOrder',
                aggregateId: $workOrder->id->value,
                payload:     [
                    'work_order_no'      => $workOrderNo,
                    'bom_id'             => $bom->id->value,
                    'bom_code'           => $bom->code,
                    'quantity_to_produce' => $workOrder->quantityToProduce,
                    'lines_count'        => count($workOrder->lines),
                ],
            );

            return $workOrder;
        });
    }
}
