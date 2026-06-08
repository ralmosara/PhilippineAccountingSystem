<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Inventory\Application\Actions\RecordStockMovement;
use App\Modules\Inventory\Application\Contracts\WarehouseRepositoryContract;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use App\Modules\Sales\Domain\Events\InvoiceIssued;
use Illuminate\Support\Facades\DB;

/**
 * On InvoiceIssued (Sales), iterate the invoice lines that reference an
 * item and create matching stock_movements with movement_type='issue'.
 *
 * Synchronous (not queued) — runs inside the Sales transaction so a stock
 * shortage rolls back the invoice. Override available via
 * `inventory.allow_negative_stock` permission for force-issue scenarios.
 */
final readonly class DeductStockOnInvoiceIssued
{
    public function __construct(
        private RecordStockMovement $recordMovement,
        private WarehouseRepositoryContract $warehouses,
    ) {
    }

    public function handle(InvoiceIssued $event): void
    {
        $default = $this->warehouses->findDefaultFor($event->companyId);
        if (! $default) {
            return;                                 // no warehouse configured — skip
        }

        $lines = DB::table('sales.sales_invoice_lines')
            ->where('sales_invoice_id', $event->salesInvoiceId)
            ->whereNotNull('item_id')
            ->get(['item_id', 'quantity', 'description', 'project_id']);

        foreach ($lines as $line) {
            // Skip non-inventory items silently (ItemNotInventoriedException is thrown,
            // but it's expected for services — we catch and continue)
            try {
                $this->recordMovement->execute(
                    itemId:        $line->item_id,
                    warehouseId:   $default->id->value,
                    movementType:  MovementType::Issue,
                    quantity:      (string) $line->quantity,
                    unitCost:      null,                       // outbound uses current MA cost
                    movedAt:       null,
                    sourceDocId:   $event->salesInvoiceId,
                    sourceDocType: 'SalesInvoice',
                    projectId:     $line->project_id,
                    remarks:       "Sale: {$event->docNo} — {$line->description}",
                    actorId:       $event->issuedBy,
                );
            } catch (\App\Modules\Inventory\Domain\Exceptions\ItemNotInventoriedException) {
                continue;
            }
        }
    }
}
