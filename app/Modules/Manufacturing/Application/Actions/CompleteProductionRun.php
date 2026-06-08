<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Manufacturing\Application\Contracts\WorkOrderRepositoryContract;
use App\Modules\Manufacturing\Domain\Entities\ProductionRunLine;
use App\Modules\Manufacturing\Domain\Entities\WorkOrder;
use App\Modules\Manufacturing\Domain\Events\ProductionRunPosted;
use App\Modules\Manufacturing\Domain\Events\WorkOrderCompleted;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderId;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * CompleteProductionRun — the workhorse action for finishing a work order.
 *
 * Steps (all in a single DB transaction):
 *  1. Validate work order is in_progress or released
 *  2. For each production run line: check stock, compute cost from moving-avg
 *  3. Build journal entry: DR WIP (finished goods) / CR Raw Materials (per component)
 *     CR Labor Payable / CR Manufacturing Overhead Payable
 *  4. Insert JV into accounting.journal_entries + accounting.journal_lines
 *  5. Insert stock movement records (consumption per component, production for FG)
 *  6. Update inventory.stock_balances (reduce consumed components, increase finished good)
 *  7. Mark WorkOrder completed, set actual_end, journal_entry_id
 *  8. Audit workorder.completed
 *  9. Dispatch WorkOrderCompleted + ProductionRunPosted events
 */
final readonly class CompleteProductionRun
{
    public function __construct(
        private WorkOrderRepositoryContract $workOrders,
        private AuditWriterContract         $audit,
    ) {
    }

    public function execute(
        string $workOrderId,
        string $quantityProduced,
        string $actorId,
    ): WorkOrder {
        $workOrder = $this->workOrders->findById(new WorkOrderId($workOrderId));

        if ($workOrder === null) {
            throw new InvalidArgumentException("Work order {$workOrderId} not found.");
        }

        if (! in_array($workOrder->status, [WorkOrderStatus::InProgress, WorkOrderStatus::Released], true)) {
            throw new InvalidArgumentException(
                "Work order {$workOrder->workOrderNo} must be in_progress or released to complete; ".
                "current status: {$workOrder->status->value}."
            );
        }

        return DB::transaction(function () use ($workOrder, $quantityProduced, $actorId) {
            $now = new DateTimeImmutable();

            // ── Step 1: fetch the BOM to get labor/overhead costs ─────────────
            $bom = DB::selectOne(
                'SELECT standard_batch_size, labor_cost_per_batch, overhead_cost_per_batch
                   FROM manufacturing.bills_of_materials
                  WHERE id = ?',
                [$workOrder->bomId->value],
            );

            $standardBatchSize      = (string) ($bom->standard_batch_size ?? '1');
            $laborCostPerBatch      = (string) ($bom->labor_cost_per_batch ?? '0');
            $overheadCostPerBatch   = (string) ($bom->overhead_cost_per_batch ?? '0');

            // Batch multiplier = quantityProduced / standardBatchSize
            $batchMultiplier = bcdiv($quantityProduced, $standardBatchSize, 6);

            // Pro-rated labor and overhead
            $laborCost    = bcmul($laborCostPerBatch, $batchMultiplier, 2);
            $overheadCost = bcmul($overheadCostPerBatch, $batchMultiplier, 2);

            // ── Step 2: Process each production run line ──────────────────────
            $updatedLines = [];

            foreach ($workOrder->lines as $line) {
                // Scale quantity consumed by ratio of qty_produced / qty_to_produce
                $qtyConsumed = bcmul(
                    $line->quantityRequired,
                    bcdiv($quantityProduced, $workOrder->quantityToProduce, 6),
                    4
                );

                // Fetch current stock balance (FOR UPDATE to lock the row)
                $balance = DB::selectOne(
                    'SELECT sb.id, sb.quantity, sb.value, i.moving_avg_cost
                       FROM inventory.stock_balances sb
                       JOIN inventory.items i ON i.id = sb.item_id
                      WHERE sb.item_id = ?
                        AND sb.warehouse_id = ?
                      FOR UPDATE',
                    [$line->componentItemId, $workOrder->warehouseId ?? $line->componentItemId],
                );

                // If no balance row (item may not be tracked in this warehouse), use avg from items
                if ($balance === null) {
                    $itemRow = DB::selectOne(
                        'SELECT moving_avg_cost FROM inventory.items WHERE id = ?',
                        [$line->componentItemId],
                    );
                    $movingAvgCost = (string) ($itemRow->moving_avg_cost ?? '0');
                } else {
                    $movingAvgCost = (string) $balance->moving_avg_cost;
                }

                $unitCost  = $movingAvgCost;
                $totalCost = bcmul($qtyConsumed, $unitCost, 2);

                $updatedLines[] = new ProductionRunLine(
                    id:               $line->id,
                    workOrderId:      $workOrder->id,
                    componentItemId:  $line->componentItemId,
                    componentName:    $line->componentName,
                    quantityRequired: $line->quantityRequired,
                    quantityConsumed: $qtyConsumed,
                    unitCost:         Money::php($unitCost),
                    totalCost:        Money::php($totalCost),
                );
            }

            $workOrder->lines = $updatedLines;
            $workOrder->computeTotals($laborCost, $overheadCost);
            $workOrder->quantityProduced = bcadd($quantityProduced, '0', 4);

            // ── Step 3+4: Build and insert journal entry ──────────────────────
            $jeId       = Uuid::uuid4()->toString();
            $entryDate  = $now->format('Y-m-d');
            $postedAtTs = $now->format('Y-m-d H:i:sP');

            // Determine the fiscal period for the entry date
            $period = DB::selectOne(
                'SELECT id FROM accounting.fiscal_periods
                  WHERE company_id = ?
                    AND start_date <= ?
                    AND end_date >= ?
                    AND locked_at IS NULL
                  LIMIT 1',
                [$workOrder->companyId, $entryDate, $entryDate],
            );

            $fiscalPeriodId = $period?->id ?? null;

            // Find or pick a document series for manufacturing JVs
            $docSeries = DB::selectOne(
                "SELECT id, prefix, last_sequence
                   FROM accounting.document_series
                  WHERE company_id = ?
                    AND type = 'JV'
                  LIMIT 1",
                [$workOrder->companyId],
            );

            $docSeriesId = $docSeries?->id ?? null;
            $seqNo       = 0;
            $docNo       = 'MFG-' . $now->format('YmdHis');

            if ($docSeriesId !== null) {
                $seqRow = DB::selectOne(
                    'SELECT accounting.allocate_doc_no(?::uuid) AS seq',
                    [$docSeriesId],
                );
                $seqNo = (int) ($seqRow->seq ?? 0);
                $prefix = $docSeries->prefix ?? 'JV';
                $docNo  = $prefix . str_pad((string) $seqNo, 6, '0', STR_PAD_LEFT);
            }

            // Insert journal entry header
            if ($fiscalPeriodId !== null && $docSeriesId !== null) {
                DB::table('accounting.journal_entries')->insert([
                    'id'                 => $jeId,
                    'company_id'         => $workOrder->companyId,
                    'fiscal_period_id'   => $fiscalPeriodId,
                    'document_series_id' => $docSeriesId,
                    'sequence_no'        => $seqNo,
                    'doc_no'             => $docNo,
                    'entry_date'         => $entryDate,
                    'memo'               => "Production run: WO {$workOrder->workOrderNo} — Qty {$quantityProduced}",
                    'source'             => 'manual',
                    'source_doc_id'      => $workOrder->id->value,
                    'source_doc_type'    => 'WorkOrder',
                    'posted_at'          => $postedAtTs,
                    'posted_by'          => $actorId,
                    'created_by'         => $actorId,
                    'updated_by'         => $actorId,
                    'created_at'         => $postedAtTs,
                    'updated_at'         => $postedAtTs,
                ]);

                // Build journal lines
                $lineNo      = 1;
                $jeLineRows  = [];

                // DR Finished Goods (or WIP) = total production cost
                $fgAccountId = $workOrder->finishedGoodsAccountId ?? $workOrder->wipAccountId;
                if ($fgAccountId !== null) {
                    $jeLineRows[] = [
                        'id'               => Uuid::uuid4()->toString(),
                        'journal_entry_id' => $jeId,
                        'line_no'          => $lineNo++,
                        'account_id'       => $fgAccountId,
                        'currency'         => 'PHP',
                        'debit'            => $workOrder->totalProductionCost,
                        'credit'           => '0',
                        'fx_rate'          => '1',
                        'php_amount'       => $workOrder->totalProductionCost,
                        'memo'             => "Finished goods: WO {$workOrder->workOrderNo}",
                        'created_at'       => $postedAtTs,
                        'updated_at'       => $postedAtTs,
                    ];
                }

                // CR Raw Materials Inventory — one line per component
                foreach ($workOrder->lines as $runLine) {
                    $rmAccountId = $workOrder->rawMaterialsAccountId;
                    if ($rmAccountId !== null && bccomp($runLine->totalCost->amount, '0', 2) > 0) {
                        $jeLineRows[] = [
                            'id'               => Uuid::uuid4()->toString(),
                            'journal_entry_id' => $jeId,
                            'line_no'          => $lineNo++,
                            'account_id'       => $rmAccountId,
                            'currency'         => 'PHP',
                            'debit'            => '0',
                            'credit'           => $runLine->totalCost->toPhp(),
                            'fx_rate'          => '1',
                            'php_amount'       => bcmul($runLine->totalCost->toPhp(), '-1', 2),
                            'memo'             => "Component: {$runLine->componentName}",
                            'created_at'       => $postedAtTs,
                            'updated_at'       => $postedAtTs,
                        ];
                    }
                }

                // CR Labor Payable
                if ($workOrder->wipAccountId !== null && bccomp($workOrder->totalLaborCost, '0', 2) > 0) {
                    $jeLineRows[] = [
                        'id'               => Uuid::uuid4()->toString(),
                        'journal_entry_id' => $jeId,
                        'line_no'          => $lineNo++,
                        'account_id'       => $workOrder->wipAccountId,
                        'currency'         => 'PHP',
                        'debit'            => '0',
                        'credit'           => $workOrder->totalLaborCost,
                        'fx_rate'          => '1',
                        'php_amount'       => bcmul($workOrder->totalLaborCost, '-1', 2),
                        'memo'             => "Labor cost: WO {$workOrder->workOrderNo}",
                        'created_at'       => $postedAtTs,
                        'updated_at'       => $postedAtTs,
                    ];
                }

                // CR Manufacturing Overhead Payable
                if ($workOrder->wipAccountId !== null && bccomp($workOrder->totalOverheadCost, '0', 2) > 0) {
                    $jeLineRows[] = [
                        'id'               => Uuid::uuid4()->toString(),
                        'journal_entry_id' => $jeId,
                        'line_no'          => $lineNo++,
                        'account_id'       => $workOrder->wipAccountId,
                        'currency'         => 'PHP',
                        'debit'            => '0',
                        'credit'           => $workOrder->totalOverheadCost,
                        'fx_rate'          => '1',
                        'php_amount'       => bcmul($workOrder->totalOverheadCost, '-1', 2),
                        'memo'             => "Overhead cost: WO {$workOrder->workOrderNo}",
                        'created_at'       => $postedAtTs,
                        'updated_at'       => $postedAtTs,
                    ];
                }

                if (count($jeLineRows) > 0) {
                    DB::table('accounting.journal_lines')->insert($jeLineRows);
                }

                $workOrder->journalEntryId = $jeId;
            }

            // ── Step 5+6: Stock movements + balance updates ───────────────────
            $warehouseId = $workOrder->warehouseId;

            foreach ($workOrder->lines as $runLine) {
                if ($warehouseId === null) {
                    continue;
                }

                $qtyConsumed  = $runLine->quantityConsumed;
                $unitCostStr  = $runLine->unitCost->toPhp(4);
                $totalCostStr = $runLine->totalCost->toPhp();

                // Insert consumption movement (negative quantity = outbound)
                DB::table('inventory.stock_movements')->insert([
                    'id'              => Uuid::uuid4()->toString(),
                    'item_id'         => $runLine->componentItemId,
                    'warehouse_id'    => $warehouseId,
                    'movement_type'   => 'consumption',
                    'quantity'        => bcmul($qtyConsumed, '-1', 4),
                    'unit_cost'       => $unitCostStr,
                    'total_cost'      => bcmul($totalCostStr, '-1', 2),
                    'source_doc_id'   => $workOrder->id->value,
                    'source_doc_type' => 'WorkOrder',
                    'moved_at'        => $postedAtTs,
                    'moved_by'        => $actorId,
                    'remarks'         => "Consumed for WO {$workOrder->workOrderNo}",
                    'created_at'      => $postedAtTs,
                    'updated_at'      => $postedAtTs,
                ]);

                // Update stock balance for consumed component
                // We need to temporarily bypass the no_negative_stock constraint if allowed,
                // but we'll just do a direct update and let Postgres validate.
                DB::statement(
                    'UPDATE inventory.stock_balances
                        SET quantity         = quantity - ?,
                            value            = value - ?,
                            last_movement_at = ?,
                            updated_at       = ?
                      WHERE item_id = ?
                        AND warehouse_id = ?',
                    [
                        $qtyConsumed,
                        $totalCostStr,
                        $postedAtTs,
                        $postedAtTs,
                        $runLine->componentItemId,
                        $warehouseId,
                    ]
                );
            }

            // Insert production movement for finished good (positive = inbound)
            // We need to know the finished good item_id from the BOM
            $bomItemRow = DB::selectOne(
                'SELECT item_id FROM manufacturing.bills_of_materials WHERE id = ?',
                [$workOrder->bomId->value],
            );

            if ($bomItemRow !== null && $warehouseId !== null) {
                $fgItemId    = $bomItemRow->item_id;
                $unitFgCost  = bcdiv($workOrder->totalProductionCost, $quantityProduced, 4);

                DB::table('inventory.stock_movements')->insert([
                    'id'              => Uuid::uuid4()->toString(),
                    'item_id'         => $fgItemId,
                    'warehouse_id'    => $warehouseId,
                    'movement_type'   => 'production',
                    'quantity'        => bcadd($quantityProduced, '0', 4),
                    'unit_cost'       => $unitFgCost,
                    'total_cost'      => $workOrder->totalProductionCost,
                    'source_doc_id'   => $workOrder->id->value,
                    'source_doc_type' => 'WorkOrder',
                    'moved_at'        => $postedAtTs,
                    'moved_by'        => $actorId,
                    'remarks'         => "Produced by WO {$workOrder->workOrderNo}",
                    'created_at'      => $postedAtTs,
                    'updated_at'      => $postedAtTs,
                ]);

                // Update or insert finished good balance
                $fgBalance = DB::selectOne(
                    'SELECT id, quantity, value FROM inventory.stock_balances
                      WHERE item_id = ? AND warehouse_id = ? FOR UPDATE',
                    [$fgItemId, $warehouseId],
                );

                if ($fgBalance !== null) {
                    $newQty   = bcadd((string) $fgBalance->quantity, $quantityProduced, 4);
                    $newValue = bcadd((string) $fgBalance->value, $workOrder->totalProductionCost, 2);

                    // Recalculate moving average cost = new_value / new_qty
                    $newMovingAvg = bccomp($newQty, '0', 4) > 0
                        ? bcdiv($newValue, $newQty, 4)
                        : $unitFgCost;

                    DB::statement(
                        'UPDATE inventory.stock_balances
                            SET quantity         = ?,
                                value            = ?,
                                last_movement_at = ?,
                                updated_at       = ?
                          WHERE id = ?',
                        [$newQty, $newValue, $postedAtTs, $postedAtTs, $fgBalance->id]
                    );

                    // Update moving_avg_cost on the items table
                    DB::statement(
                        'UPDATE inventory.items SET moving_avg_cost = ?, updated_at = ? WHERE id = ?',
                        [$newMovingAvg, $postedAtTs, $fgItemId]
                    );
                } else {
                    // Create new balance record
                    DB::table('inventory.stock_balances')->insert([
                        'id'              => Uuid::uuid4()->toString(),
                        'item_id'         => $fgItemId,
                        'warehouse_id'    => $warehouseId,
                        'quantity'        => bcadd($quantityProduced, '0', 4),
                        'value'           => $workOrder->totalProductionCost,
                        'last_movement_at' => $postedAtTs,
                        'created_at'      => $postedAtTs,
                        'updated_at'      => $postedAtTs,
                    ]);

                    DB::statement(
                        'UPDATE inventory.items SET moving_avg_cost = ?, updated_at = ? WHERE id = ?',
                        [$unitFgCost, $postedAtTs, $fgItemId]
                    );
                }
            }

            // ── Step 7: Mark work order completed ────────────────────────────
            $workOrder->complete($actorId);

            $this->workOrders->save($workOrder);

            // ── Step 8: Audit ────────────────────────────────────────────────
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $workOrder->companyId,
                eventType:   'workorder.completed',
                aggregate:   'WorkOrder',
                aggregateId: $workOrder->id->value,
                payload:     [
                    'work_order_no'        => $workOrder->workOrderNo,
                    'quantity_produced'    => $workOrder->quantityProduced,
                    'total_material_cost'  => $workOrder->totalMaterialCost,
                    'total_labor_cost'     => $workOrder->totalLaborCost,
                    'total_overhead_cost'  => $workOrder->totalOverheadCost,
                    'total_production_cost' => $workOrder->totalProductionCost,
                    'journal_entry_id'     => $workOrder->journalEntryId,
                ],
            );

            // ── Step 9: Dispatch domain events ───────────────────────────────
            Event::dispatch(new WorkOrderCompleted(
                workOrderId:         $workOrder->id->value,
                workOrderNo:         $workOrder->workOrderNo,
                companyId:           $workOrder->companyId,
                quantityProduced:    $workOrder->quantityProduced,
                totalProductionCost: $workOrder->totalProductionCost,
                journalEntryId:      $workOrder->journalEntryId,
                completedAt:         $workOrder->actualEnd?->format(\DateTimeInterface::ATOM) ?? $now->format(\DateTimeInterface::ATOM),
                actorId:             $actorId,
            ));

            if ($workOrder->journalEntryId !== null) {
                Event::dispatch(new ProductionRunPosted(
                    workOrderId:         $workOrder->id->value,
                    workOrderNo:         $workOrder->workOrderNo,
                    companyId:           $workOrder->companyId,
                    journalEntryId:      $workOrder->journalEntryId,
                    totalProductionCost: $workOrder->totalProductionCost,
                    linesConsumed:       count($workOrder->lines),
                    postedAt:            $postedAtTs,
                ));
            }

            return $workOrder;
        });
    }
}
