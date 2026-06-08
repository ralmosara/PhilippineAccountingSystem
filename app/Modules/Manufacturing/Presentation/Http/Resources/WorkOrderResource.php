<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Resources;

use App\Modules\Manufacturing\Domain\Entities\WorkOrder;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderStatus;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\WorkOrderModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Accepts either a Domain WorkOrder or an Eloquent WorkOrderModel.
 */
final class WorkOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof WorkOrder) {
            return $this->fromDomain($this->resource);
        }

        return $this->fromModel($this->resource);
    }

    /** @return array<string, mixed> */
    private function fromDomain(WorkOrder $wo): array
    {
        return [
            'id'                       => $wo->id->value,
            'company_id'               => $wo->companyId,
            'bom_id'                   => $wo->bomId->value,
            'work_order_no'            => $wo->workOrderNo,
            'quantity_to_produce'      => $wo->quantityToProduce,
            'quantity_produced'        => $wo->quantityProduced,
            'status'                   => $wo->status->value,
            'status_label'             => $wo->status->label(),
            'status_badge'             => self::badgeClass($wo->status),
            'scheduled_start'          => $wo->scheduledStart,
            'scheduled_end'            => $wo->scheduledEnd,
            'actual_start'             => $wo->actualStart?->format(\DateTimeInterface::ATOM),
            'actual_end'               => $wo->actualEnd?->format(\DateTimeInterface::ATOM),
            'warehouse_id'             => $wo->warehouseId,
            'wip_account_id'           => $wo->wipAccountId,
            'finished_goods_account_id' => $wo->finishedGoodsAccountId,
            'raw_materials_account_id' => $wo->rawMaterialsAccountId,
            'total_material_cost'      => $wo->totalMaterialCost,
            'total_labor_cost'         => $wo->totalLaborCost,
            'total_overhead_cost'      => $wo->totalOverheadCost,
            'total_production_cost'    => $wo->totalProductionCost,
            'journal_entry_id'         => $wo->journalEntryId,
            'lines' => array_map(fn ($l) => [
                'id'                 => $l->id,
                'component_item_id'  => $l->componentItemId,
                'component_name'     => $l->componentName,
                'quantity_required'  => $l->quantityRequired,
                'quantity_consumed'  => $l->quantityConsumed,
                'unit_cost'          => $l->unitCost->toPhp(4),
                'total_cost'         => $l->totalCost->toPhp(),
            ], $wo->lines),
        ];
    }

    /** @return array<string, mixed> */
    private function fromModel(WorkOrderModel $model): array
    {
        $status = WorkOrderStatus::from($model->status);

        return [
            'id'                       => $model->id,
            'company_id'               => $model->company_id,
            'bom_id'                   => $model->bom_id,
            'work_order_no'            => $model->work_order_no,
            'quantity_to_produce'      => (string) $model->quantity_to_produce,
            'quantity_produced'        => (string) $model->quantity_produced,
            'status'                   => $model->status,
            'status_label'             => $status->label(),
            'status_badge'             => self::badgeClass($status),
            'scheduled_start'          => $model->scheduled_start?->format('Y-m-d'),
            'scheduled_end'            => $model->scheduled_end?->format('Y-m-d'),
            'actual_start'             => $model->actual_start?->toIso8601String(),
            'actual_end'               => $model->actual_end?->toIso8601String(),
            'warehouse_id'             => $model->warehouse_id,
            'wip_account_id'           => $model->wip_account_id,
            'finished_goods_account_id' => $model->finished_goods_account_id,
            'raw_materials_account_id' => $model->raw_materials_account_id,
            'total_material_cost'      => (string) $model->total_material_cost,
            'total_labor_cost'         => (string) $model->total_labor_cost,
            'total_overhead_cost'      => (string) $model->total_overhead_cost,
            'total_production_cost'    => (string) $model->total_production_cost,
            'journal_entry_id'         => $model->journal_entry_id,
            'lines' => $this->whenLoaded('lines', fn () => $model->lines->map(fn ($l) => [
                'id'                 => $l->id,
                'component_item_id'  => $l->component_item_id,
                'component_name'     => $l->component_name,
                'quantity_required'  => (string) $l->quantity_required,
                'quantity_consumed'  => (string) $l->quantity_consumed,
                'unit_cost'          => (string) $l->unit_cost,
                'total_cost'         => (string) $l->total_cost,
            ])),
        ];
    }

    private static function badgeClass(WorkOrderStatus $status): string
    {
        return match ($status) {
            WorkOrderStatus::Draft      => 'badge-slate',
            WorkOrderStatus::Released   => 'badge-blue',
            WorkOrderStatus::InProgress => 'badge-amber',
            WorkOrderStatus::Completed  => 'badge-green',
            WorkOrderStatus::Cancelled  => 'badge-red',
        };
    }
}
