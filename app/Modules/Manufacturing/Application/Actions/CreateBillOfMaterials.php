<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Manufacturing\Application\Contracts\BomRepositoryContract;
use App\Modules\Manufacturing\Domain\Entities\BillOfMaterials;
use App\Modules\Manufacturing\Domain\Entities\BomLine;
use App\Modules\Manufacturing\Domain\ValueObjects\BomId;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class CreateBillOfMaterials
{
    public function __construct(
        private BomRepositoryContract $boms,
        private AuditWriterContract   $audit,
    ) {
    }

    /**
     * @param  array<int, array{
     *     component_item_id: string,
     *     component_name: string,
     *     quantity_per_batch: string|float|int,
     *     unit_of_measure: string,
     *     notes?: string|null,
     * }>  $lines
     */
    public function execute(
        string  $companyId,
        string  $itemId,
        string  $itemName,
        string  $code,
        string  $name,
        string  $version,
        string  $standardBatchSize,
        string  $laborCostPerBatch,
        string  $overheadCostPerBatch,
        array   $lines,
        ?string $notes = null,
        ?string $actorId = null,
    ): BillOfMaterials {
        // Validate code uniqueness within company
        $existing = $this->boms->findByCode($companyId, $code);
        if ($existing !== null) {
            throw new InvalidArgumentException("BOM code '{$code}' already exists for this company.");
        }

        return DB::transaction(function () use (
            $companyId, $itemId, $itemName, $code, $name, $version,
            $standardBatchSize, $laborCostPerBatch, $overheadCostPerBatch,
            $lines, $notes, $actorId
        ) {
            $bom = new BillOfMaterials(
                id:                   BomId::generate(),
                companyId:            $companyId,
                itemId:               $itemId,
                itemName:             $itemName,
                code:                 $code,
                name:                 $name,
                version:              $version,
                status:               'draft',
                standardBatchSize:    bcadd($standardBatchSize, '0', 4),
                laborCostPerBatch:    bcadd($laborCostPerBatch, '0', 2),
                overheadCostPerBatch: bcadd($overheadCostPerBatch, '0', 2),
                notes:                $notes,
            );

            foreach ($lines as $lineData) {
                $bom->addLine(new BomLine(
                    id:               Uuid::uuid4()->toString(),
                    bomId:            $bom->id,
                    componentItemId:  $lineData['component_item_id'],
                    componentName:    $lineData['component_name'],
                    quantityPerBatch: bcadd((string) $lineData['quantity_per_batch'], '0', 4),
                    unitOfMeasure:    $lineData['unit_of_measure'],
                    notes:            $lineData['notes'] ?? null,
                ));
            }

            $this->boms->save($bom);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'bom.created',
                aggregate:   'BillOfMaterials',
                aggregateId: $bom->id->value,
                payload:     [
                    'code'                 => $bom->code,
                    'name'                 => $bom->name,
                    'item_id'              => $bom->itemId,
                    'standard_batch_size'  => $bom->standardBatchSize,
                    'lines_count'          => count($bom->lines),
                ],
            );

            return $bom;
        });
    }
}
