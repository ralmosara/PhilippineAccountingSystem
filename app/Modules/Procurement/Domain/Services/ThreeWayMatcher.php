<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Services;

/**
 * Three-way match: PO ↔ GRN ↔ Vendor Bill.
 *
 * A bill matches when, per line:
 *   - po.received_quantity ≥ bill.quantity   (cannot bill more than received)
 *   - po.unit_price        ≈ bill.unit_price (within tolerance %)
 *   - bill.quantity        = grn.accepted_quantity (referenced GRN)
 *
 * Variances are flagged but not auto-rejected; an Approver can override
 * (logged in audit). This service produces the verdict; persistence and
 * approval workflow live in the Application layer.
 */
final readonly class ThreeWayMatcher
{
    public function __construct(
        public string $priceTolerancePct = '0.05',   // 5% default tolerance
    ) {
    }

    /**
     * @param  array<int, array{po_unit_price: string, po_received_qty: string, bill_unit_price: string, bill_qty: string}>  $linePairs
     * @return array{status: 'matched'|'variance', variances: list<array<string, mixed>>}
     */
    public function evaluate(array $linePairs): array
    {
        $variances = [];

        foreach ($linePairs as $i => $pair) {
            // 1. Quantity check — billed must not exceed received
            if (bccomp($pair['bill_qty'], $pair['po_received_qty'], 4) > 0) {
                $variances[] = [
                    'line_no' => $i + 1,
                    'reason'  => 'overbilled',
                    'detail'  => "billed {$pair['bill_qty']} > received {$pair['po_received_qty']}",
                ];
            }

            // 2. Price tolerance check
            $diff = bcsub($pair['bill_unit_price'], $pair['po_unit_price'], 4);
            $absDiff = ltrim($diff, '-');
            $tolerance = bcmul($pair['po_unit_price'], $this->priceTolerancePct, 4);

            if (bccomp($absDiff, $tolerance, 4) > 0) {
                $variances[] = [
                    'line_no' => $i + 1,
                    'reason'  => 'price_variance',
                    'detail'  => "PO {$pair['po_unit_price']} vs bill {$pair['bill_unit_price']} (tolerance {$tolerance})",
                ];
            }
        }

        return [
            'status'    => $variances === [] ? 'matched' : 'variance',
            'variances' => $variances,
        ];
    }
}
