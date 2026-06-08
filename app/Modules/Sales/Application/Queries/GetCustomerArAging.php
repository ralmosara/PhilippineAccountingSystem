<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Queries;

use App\Modules\Sales\Application\DTOs\CustomerArAging;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Computes AR aging for one customer in a single SQL pass.
 *
 * Read-only / CQRS-lite — bypasses the domain layer because aging is a
 * reporting concern, not a transactional invariant. Client also computes
 * an approximation; this endpoint is authoritative.
 *
 * Algorithm:
 *   1. Sum total per posted-non-voided invoice (= invoice.total).
 *   2. Sum ORs booked against each invoice (= sum(or.amount)).
 *   3. balance = total − paid; if ≤ 0 → invoice is fully paid, skip.
 *   4. days_overdue = $asOf − coalesce(due_date, invoice_date)
 *   5. bucket the balance into current / 1-30 / 31-60 / 61-90 / 91+
 */
final readonly class GetCustomerArAging
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function execute(string $customerId, DateTimeImmutable $asOf): CustomerArAging
    {
        // Pull each posted-non-voided invoice with its sum-of-ORs and bucket it
        // by days_overdue. The CASE arithmetic happens server-side in Postgres
        // for performance on customers with hundreds of open invoices.
        $rows = $this->db->select(<<<'SQL'
            WITH receipts AS (
                SELECT sales_invoice_id, COALESCE(SUM(amount), 0) AS paid_total
                  FROM sales.official_receipts
                 WHERE customer_id = ?::uuid
                 GROUP BY sales_invoice_id
            )
            SELECT
                i.id,
                i.total                                        AS invoice_total,
                COALESCE(r.paid_total, 0)                      AS paid_total,
                (i.total - COALESCE(r.paid_total, 0))          AS balance,
                COALESCE(i.due_date, i.invoice_date)           AS due_date,
                (?::date - COALESCE(i.due_date, i.invoice_date)) AS days_overdue
            FROM sales.sales_invoices i
            LEFT JOIN receipts r ON r.sales_invoice_id = i.id
            WHERE i.customer_id = ?::uuid
              AND i.posted_at  IS NOT NULL
              AND i.voided_at  IS NULL
              AND (i.total - COALESCE(r.paid_total, 0)) > 0
        SQL, [$customerId, $asOf->format('Y-m-d'), $customerId]);

        $current      = '0.00';
        $bucket_1_30  = '0.00';
        $bucket_31_60 = '0.00';
        $bucket_61_90 = '0.00';
        $bucket_91p   = '0.00';
        $total        = '0.00';

        foreach ($rows as $r) {
            $balance     = (string) $r->balance;
            $daysOverdue = (int) $r->days_overdue;

            if      ($daysOverdue <= 0)   $current      = bcadd($current,      $balance, 2);
            elseif  ($daysOverdue <= 30)  $bucket_1_30  = bcadd($bucket_1_30,  $balance, 2);
            elseif  ($daysOverdue <= 60)  $bucket_31_60 = bcadd($bucket_31_60, $balance, 2);
            elseif  ($daysOverdue <= 90)  $bucket_61_90 = bcadd($bucket_61_90, $balance, 2);
            else                           $bucket_91p   = bcadd($bucket_91p,   $balance, 2);

            $total = bcadd($total, $balance, 2);
        }

        return new CustomerArAging(
            customerId:         $customerId,
            asOf:               $asOf,
            current:            $current,
            bucket_1_30:        $bucket_1_30,
            bucket_31_60:       $bucket_31_60,
            bucket_61_90:       $bucket_61_90,
            bucket_91_plus:     $bucket_91p,
            total:              $total,
            unpaidInvoiceCount: count($rows),
        );
    }
}
