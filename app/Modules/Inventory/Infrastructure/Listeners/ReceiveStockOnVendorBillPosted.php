<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Inventory\Application\Actions\RecordStockMovement;
use App\Modules\Inventory\Application\Contracts\WarehouseRepositoryContract;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use App\Modules\Procurement\Domain\Events\VendorBillPosted;
use Illuminate\Support\Facades\DB;

/**
 * On VendorBillPosted, create stock receipts for each line referencing
 * an inventory item, using the bill's unit_price as the receipt cost.
 *
 * The moving-avg calculator updates the item's moving_avg_cost
 * automatically — this is how cost rolls forward as new shipments arrive.
 */
final readonly class ReceiveStockOnVendorBillPosted
{
    public function __construct(
        private RecordStockMovement $recordMovement,
        private WarehouseRepositoryContract $warehouses,
    ) {
    }

    public function handle(VendorBillPosted $event): void
    {
        $default = $this->warehouses->findDefaultFor($event->companyId);
        if (! $default) {
            return;
        }

        $lines = DB::table('procurement.vendor_bill_lines')
            ->where('vendor_bill_id', $event->vendorBillId)
            ->whereNotNull('item_id')
            ->get(['item_id', 'quantity', 'unit_price', 'description', 'project_id']);

        foreach ($lines as $line) {
            try {
                $this->recordMovement->execute(
                    itemId:        $line->item_id,
                    warehouseId:   $default->id->value,
                    movementType:  MovementType::Receipt,
                    quantity:      (string) $line->quantity,
                    unitCost:      Money::php((string) $line->unit_price),
                    movedAt:       null,
                    sourceDocId:   $event->vendorBillId,
                    sourceDocType: 'VendorBill',
                    projectId:     $line->project_id,
                    remarks:       "Receipt from vendor bill {$event->vendorInvoiceNo} — {$line->description}",
                    actorId:       $event->postedBy,
                );
            } catch (\App\Modules\Inventory\Domain\Exceptions\ItemNotInventoriedException) {
                continue;
            }
        }
    }
}
