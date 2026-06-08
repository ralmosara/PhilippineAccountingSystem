<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Presentation\Http\Resources;

use App\Modules\Manufacturing\Domain\Entities\BillOfMaterials;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\BomModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Accepts either a Domain BillOfMaterials or an Eloquent BomModel.
 */
final class BomResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof BillOfMaterials) {
            return $this->fromDomain($this->resource);
        }

        return $this->fromModel($this->resource);
    }

    /** @return array<string, mixed> */
    private function fromDomain(BillOfMaterials $bom): array
    {
        return [
            'id'                      => $bom->id->value,
            'company_id'              => $bom->companyId,
            'item_id'                 => $bom->itemId,
            'item_name'               => $bom->itemName,
            'code'                    => $bom->code,
            'name'                    => $bom->name,
            'version'                 => $bom->version,
            'status'                  => $bom->status,
            'standard_batch_size'     => $bom->standardBatchSize,
            'labor_cost_per_batch'    => $bom->laborCostPerBatch,
            'overhead_cost_per_batch' => $bom->overheadCostPerBatch,
            'notes'                   => $bom->notes,
            'lines' => array_map(fn ($l) => [
                'id'                 => $l->id,
                'component_item_id'  => $l->componentItemId,
                'component_name'     => $l->componentName,
                'quantity_per_batch' => $l->quantityPerBatch,
                'unit_of_measure'    => $l->unitOfMeasure,
                'notes'              => $l->notes,
            ], $bom->lines),
        ];
    }

    /** @return array<string, mixed> */
    private function fromModel(BomModel $model): array
    {
        return [
            'id'                      => $model->id,
            'company_id'              => $model->company_id,
            'item_id'                 => $model->item_id,
            'item_name'               => $model->item_name,
            'code'                    => $model->code,
            'name'                    => $model->name,
            'version'                 => $model->version,
            'status'                  => $model->status,
            'standard_batch_size'     => (string) $model->standard_batch_size,
            'labor_cost_per_batch'    => (string) $model->labor_cost_per_batch,
            'overhead_cost_per_batch' => (string) $model->overhead_cost_per_batch,
            'notes'                   => $model->notes,
            'lines' => $this->whenLoaded('lines', fn () => $model->lines->map(fn ($l) => [
                'id'                 => $l->id,
                'component_item_id'  => $l->component_item_id,
                'component_name'     => $l->component_name,
                'quantity_per_batch' => (string) $l->quantity_per_batch,
                'unit_of_measure'    => $l->unit_of_measure,
                'notes'              => $l->notes,
            ])),
        ];
    }
}
