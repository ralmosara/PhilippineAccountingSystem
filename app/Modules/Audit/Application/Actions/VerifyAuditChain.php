<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Actions;

use Illuminate\Database\ConnectionInterface;

/**
 * Walks the audit chain in order, recomputes each row's hash, and reports
 * any tampering. Called daily by the scheduler and on demand by auditors.
 *
 * The query uses a window function so we don't have to pull every row into
 * PHP — Postgres does the comparison in a single CTE.
 */
final readonly class VerifyAuditChain
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    /**
     * @return array{verified: int, tampered: array<int, array{id: int, reason: string}>}
     */
    public function execute(?string $companyId = null): array
    {
        $sql = <<<'SQL'
            WITH chain AS (
                SELECT id,
                       company_id,
                       prev_hash,
                       current_hash,
                       payload,
                       LAG(current_hash) OVER (PARTITION BY company_id ORDER BY id) AS expected_prev
                  FROM audit.events
                  WHERE (?::uuid IS NULL OR company_id = ?::uuid)
            )
            SELECT id,
                   CASE
                       WHEN prev_hash IS DISTINCT FROM expected_prev
                            THEN 'broken_chain'
                       WHEN current_hash != digest(coalesce(prev_hash, ''::bytea) || convert_to(payload::text, 'UTF8'), 'sha256')
                            THEN 'hash_mismatch'
                   END AS reason
              FROM chain
             WHERE prev_hash IS DISTINCT FROM expected_prev
                OR current_hash != digest(coalesce(prev_hash, ''::bytea) || convert_to(payload::text, 'UTF8'), 'sha256')
             ORDER BY id;
        SQL;

        $tampered = $this->db->select($sql, [$companyId, $companyId]);
        $total = (int) ($this->db->selectOne(
            'SELECT count(*) AS n FROM audit.events WHERE (?::uuid IS NULL OR company_id = ?::uuid)',
            [$companyId, $companyId],
        )->n ?? 0);

        return [
            'verified' => $total - count($tampered),
            'tampered' => array_map(fn ($r) => [
                'id'     => (int) $r->id,
                'reason' => (string) $r->reason,
            ], $tampered),
        ];
    }
}
