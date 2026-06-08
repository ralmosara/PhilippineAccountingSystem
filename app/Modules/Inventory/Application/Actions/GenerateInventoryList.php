<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

/**
 * Generates the BIR Annual Inventory List (RMC 57-2015 / RR 1-2018).
 *
 * Filing deadline: within 30 days from fiscal year-end (Jan 30 for
 * calendar-year filers).
 *
 * Output: CSV + PDF stored in MinIO; `tax.form_filing_log`-like row
 * in `inventory.inventory_list_submissions` for proof of generation.
 *
 * The CSV columns mirror BIR's recommended format:
 *   sku, name, uom, beginning_qty, receipts_qty, issues_qty, ending_qty,
 *   moving_avg_cost, ending_value, category
 */
final readonly class GenerateInventoryList
{
    public function __construct(private AuditWriterContract $audit)
    {
    }

    /**
     * @return array{submission_id: string, csv_path: string, item_count: int, total_value: string}
     */
    public function execute(
        string $companyId,
        int $year,
        DateTimeImmutable $asOfDate,
        string $actorId,
    ): array {
        $periodFrom = new DateTimeImmutable("{$year}-01-01");
        $periodTo   = $asOfDate;

        $rows = DB::select(<<<'SQL'
            WITH movements AS (
                SELECT m.item_id,
                       SUM(CASE WHEN m.quantity > 0 AND m.moved_at <  ?::date THEN m.quantity ELSE 0 END) AS beg_in,
                       SUM(CASE WHEN m.quantity < 0 AND m.moved_at <  ?::date THEN -m.quantity ELSE 0 END) AS beg_out,
                       SUM(CASE WHEN m.quantity > 0 AND m.moved_at BETWEEN ?::date AND ?::date THEN m.quantity ELSE 0 END) AS in_qty,
                       SUM(CASE WHEN m.quantity < 0 AND m.moved_at BETWEEN ?::date AND ?::date THEN -m.quantity ELSE 0 END) AS out_qty
                FROM inventory.stock_movements m
                GROUP BY m.item_id
            ),
            balances AS (
                SELECT b.item_id,
                       SUM(b.quantity) AS ending_qty,
                       SUM(b.value)    AS ending_value
                FROM inventory.stock_balances b
                GROUP BY b.item_id
            )
            SELECT
                i.id, i.sku, i.name, u.code AS uom_code, c.name AS category_name,
                i.moving_avg_cost,
                COALESCE(mv.beg_in,  0) - COALESCE(mv.beg_out,  0) AS beginning_qty,
                COALESCE(mv.in_qty,  0)                            AS receipts_qty,
                COALESCE(mv.out_qty, 0)                            AS issues_qty,
                COALESCE(b.ending_qty,    0)                       AS ending_qty,
                COALESCE(b.ending_value,  0)                       AS ending_value
            FROM inventory.items i
            INNER JOIN inventory.units_of_measure u ON u.id = i.uom_id
            LEFT  JOIN inventory.item_categories c  ON c.id = i.category_id
            LEFT  JOIN movements mv                 ON mv.item_id = i.id
            LEFT  JOIN balances b                   ON b.item_id  = i.id
            WHERE i.company_id = ?::uuid
              AND i.is_inventory = true
            ORDER BY c.name NULLS LAST, i.sku
        SQL, [
            $periodFrom->format('Y-m-d'),
            $periodFrom->format('Y-m-d'),
            $periodFrom->format('Y-m-d'),
            $periodTo->format('Y-m-d'),
            $periodFrom->format('Y-m-d'),
            $periodTo->format('Y-m-d'),
            $companyId,
        ]);

        // Build CSV
        $csv = "SKU,Name,UOM,Category,Beginning Qty,Receipts Qty,Issues Qty,Ending Qty,Moving Avg Cost,Ending Value\r\n";
        $totalValue = '0';
        foreach ($rows as $r) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\r\n",
                $this->csvEscape($r->sku),
                $this->csvEscape($r->name),
                $this->csvEscape($r->uom_code),
                $this->csvEscape($r->category_name ?? ''),
                number_format((float) $r->beginning_qty, 4, '.', ''),
                number_format((float) $r->receipts_qty,  4, '.', ''),
                number_format((float) $r->issues_qty,    4, '.', ''),
                number_format((float) $r->ending_qty,    4, '.', ''),
                number_format((float) $r->moving_avg_cost, 4, '.', ''),
                number_format((float) $r->ending_value,  2, '.', ''),
            );
            $totalValue = bcadd($totalValue, (string) $r->ending_value, 2);
        }

        // Persist file
        $csvPath = sprintf(
            'bir/%s/%d/inventory-list/InventoryList_%d.csv',
            $companyId, $year, $year,
        );
        Storage::put($csvPath, $csv);

        $submissionId = Uuid::uuid4()->toString();
        DB::table('inventory.inventory_list_submissions')->upsert(
            [[
                'id'             => $submissionId,
                'company_id'     => $companyId,
                'year'           => $year,
                'as_of_date'     => $asOfDate->format('Y-m-d'),
                'period_from'    => $periodFrom->format('Y-m-d'),
                'period_to'      => $periodTo->format('Y-m-d'),
                'item_count'     => count($rows),
                'total_value'    => $totalValue,
                'csv_path'       => $csvPath,
                'generated_at'   => now(),
                'generated_by'   => $actorId,
                'status'         => 'generated',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]],
            ['company_id', 'year'],
            ['as_of_date', 'period_from', 'period_to', 'item_count', 'total_value',
             'csv_path', 'generated_at', 'generated_by', 'status', 'updated_at'],
        );

        $this->audit->writeEvent(
            actorId:     $actorId,
            companyId:   $companyId,
            eventType:   'inventory.list_generated',
            aggregate:   'InventoryListSubmission',
            aggregateId: $submissionId,
            payload: [
                'year'        => $year,
                'item_count'  => count($rows),
                'total_value' => $totalValue,
                'csv_path'    => $csvPath,
            ],
        );

        return [
            'submission_id' => $submissionId,
            'csv_path'      => $csvPath,
            'item_count'    => count($rows),
            'total_value'   => $totalValue,
        ];
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }
        return $value;
    }
}
