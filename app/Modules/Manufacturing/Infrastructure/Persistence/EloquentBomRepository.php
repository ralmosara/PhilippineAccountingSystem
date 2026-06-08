<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Infrastructure\Persistence;

use App\Modules\Manufacturing\Application\Contracts\BomRepositoryContract;
use App\Modules\Manufacturing\Domain\Entities\BillOfMaterials;
use App\Modules\Manufacturing\Domain\Entities\BomLine;
use App\Modules\Manufacturing\Domain\ValueObjects\BomId;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\BomLineModel;
use App\Modules\Manufacturing\Infrastructure\Persistence\Eloquent\BomModel;
use Ramsey\Uuid\Uuid;

final class EloquentBomRepository implements BomRepositoryContract
{
    public function findById(BomId $id): ?BillOfMaterials
    {
        $model = BomModel::query()->with('lines')->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $companyId, string $code): ?BillOfMaterials
    {
        $model = BomModel::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(BillOfMaterials $bom): void
    {
        BomModel::query()->updateOrInsert(
            ['id' => $bom->id->value],
            [
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
                'updated_at'              => now(),
                'created_at'              => now(),
            ]
        );

        // Replace lines
        BomLineModel::query()->where('bom_id', $bom->id->value)->delete();

        foreach ($bom->lines as $line) {
            BomLineModel::query()->create([
                'id'                 => $line->id ?: Uuid::uuid4()->toString(),
                'bom_id'             => $bom->id->value,
                'component_item_id'  => $line->componentItemId,
                'component_name'     => $line->componentName,
                'quantity_per_batch' => $line->quantityPerBatch,
                'unit_of_measure'    => $line->unitOfMeasure,
                'notes'              => $line->notes,
            ]);
        }
    }

    /** @return list<BillOfMaterials> */
    public function listForCompany(string $companyId, int $perPage = 25, int $page = 1): array
    {
        return BomModel::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->values()
            ->all();
    }

    private function toDomain(BomModel $model): BillOfMaterials
    {
        $bom = new BillOfMaterials(
            id:                   new BomId($model->id),
            companyId:            $model->company_id,
            itemId:               $model->item_id,
            itemName:             $model->item_name,
            code:                 $model->code,
            name:                 $model->name,
            version:              $model->version,
            status:               $model->status,
            standardBatchSize:    (string) $model->standard_batch_size,
            laborCostPerBatch:    (string) $model->labor_cost_per_batch,
            overheadCostPerBatch: (string) $model->overhead_cost_per_batch,
            notes:                $model->notes,
        );

        foreach ($model->lines as $lineModel) {
            $bom->lines[] = new BomLine(
                id:               $lineModel->id,
                bomId:            $bom->id,
                componentItemId:  $lineModel->component_item_id,
                componentName:    $lineModel->component_name,
                quantityPerBatch: (string) $lineModel->quantity_per_batch,
                unitOfMeasure:    $lineModel->unit_of_measure,
                notes:            $lineModel->notes,
            );
        }

        return $bom;
    }
}
