<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Infrastructure\Persistence;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Manufacturing\Application\Contracts\WorkOrderRepositoryContract;
use App\Modules\Manufacturing\Domain\Entities\ProductionRunLine;
use App\Modules\Manufacturing\Domain\Entities\WorkOrder;
use App\Modules\Manufacturing\Domain\ValueObjects\BomId;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderId;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderStatus;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\ProductionRunLineModel;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\WorkOrderModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class EloquentWorkOrderRepository implements WorkOrderRepositoryContract
{
    public function findById(WorkOrderId $id): ?WorkOrder
    {
        $model = WorkOrderModel::query()->with('lines')->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(WorkOrder $workOrder): void
    {
        WorkOrderModel::query()->updateOrInsert(
            ['id' => $workOrder->id->value],
            [
                'company_id'               => $workOrder->companyId,
                'bom_id'                   => $workOrder->bomId->value,
                'work_order_no'            => $workOrder->workOrderNo,
                'quantity_to_produce'      => $workOrder->quantityToProduce,
                'quantity_produced'        => $workOrder->quantityProduced,
                'status'                   => $workOrder->status->value,
                'scheduled_start'          => $workOrder->scheduledStart,
                'scheduled_end'            => $workOrder->scheduledEnd,
                'actual_start'             => $workOrder->actualStart?->format('Y-m-d H:i:sP'),
                'actual_end'               => $workOrder->actualEnd?->format('Y-m-d H:i:sP'),
                'warehouse_id'             => $workOrder->warehouseId,
                'wip_account_id'           => $workOrder->wipAccountId,
                'finished_goods_account_id' => $workOrder->finishedGoodsAccountId,
                'raw_materials_account_id' => $workOrder->rawMaterialsAccountId,
                'total_material_cost'      => $workOrder->totalMaterialCost,
                'total_labor_cost'         => $workOrder->totalLaborCost,
                'total_overhead_cost'      => $workOrder->totalOverheadCost,
                'total_production_cost'    => $workOrder->totalProductionCost,
                'journal_entry_id'         => $workOrder->journalEntryId,
                'updated_at'               => now(),
                'created_at'               => now(),
            ]
        );

        // Replace production run lines
        ProductionRunLineModel::query()
            ->where('work_order_id', $workOrder->id->value)
            ->delete();

        foreach ($workOrder->lines as $line) {
            ProductionRunLineModel::query()->create([
                'id'                 => $line->id ?: Uuid::uuid4()->toString(),
                'work_order_id'      => $workOrder->id->value,
                'component_item_id'  => $line->componentItemId,
                'component_name'     => $line->componentName,
                'quantity_required'  => $line->quantityRequired,
                'quantity_consumed'  => $line->quantityConsumed,
                'unit_cost'          => $line->unitCost->toPhp(4),
                'total_cost'         => $line->totalCost->toPhp(),
            ]);
        }
    }

    /** @return list<WorkOrder> */
    public function listForCompany(string $companyId, int $perPage = 25, int $page = 1): array
    {
        return WorkOrderModel::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->values()
            ->all();
    }

    /**
     * Generates the next work order number using a SELECT FOR UPDATE on a
     * count of existing work orders for the company in the current year.
     * Format: WO-{YYYY}-{NNN} (zero-padded to 3 digits, grows as needed)
     */
    public function nextWorkOrderNo(string $companyId): string
    {
        $year = date('Y');

        $row = DB::selectOne(
            "SELECT COUNT(*) + 1 AS next_seq
               FROM manufacturing.work_orders
              WHERE company_id = ?
                AND work_order_no LIKE ?
              FOR UPDATE",
            [$companyId, "WO-{$year}-%"],
        );

        $seq = (int) ($row->next_seq ?? 1);

        return sprintf('WO-%s-%03d', $year, $seq);
    }

    private function toDomain(WorkOrderModel $model): WorkOrder
    {
        $workOrder = new WorkOrder(
            id:                     new WorkOrderId($model->id),
            companyId:              $model->company_id,
            bomId:                  new BomId($model->bom_id),
            workOrderNo:            $model->work_order_no,
            quantityToProduce:      (string) $model->quantity_to_produce,
            quantityProduced:       (string) $model->quantity_produced,
            status:                 WorkOrderStatus::from($model->status),
            scheduledStart:         $model->scheduled_start?->format('Y-m-d'),
            scheduledEnd:           $model->scheduled_end?->format('Y-m-d'),
            actualStart:            $model->actual_start ? new DateTimeImmutable($model->actual_start->toIso8601String()) : null,
            actualEnd:              $model->actual_end   ? new DateTimeImmutable($model->actual_end->toIso8601String())   : null,
            warehouseId:            $model->warehouse_id,
            wipAccountId:           $model->wip_account_id,
            finishedGoodsAccountId: $model->finished_goods_account_id,
            rawMaterialsAccountId:  $model->raw_materials_account_id,
            totalMaterialCost:      (string) $model->total_material_cost,
            totalLaborCost:         (string) $model->total_labor_cost,
            totalOverheadCost:      (string) $model->total_overhead_cost,
            totalProductionCost:    (string) $model->total_production_cost,
            journalEntryId:         $model->journal_entry_id,
        );

        foreach ($model->lines as $lineModel) {
            $workOrder->lines[] = new ProductionRunLine(
                id:               $lineModel->id,
                workOrderId:      $workOrder->id,
                componentItemId:  $lineModel->component_item_id,
                componentName:    $lineModel->component_name,
                quantityRequired: (string) $lineModel->quantity_required,
                quantityConsumed: (string) $lineModel->quantity_consumed,
                unitCost:         Money::php((string) $lineModel->unit_cost),
                totalCost:        Money::php((string) $lineModel->total_cost),
            );
        }

        return $workOrder;
    }
}
