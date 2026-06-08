<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use Illuminate\Database\ConnectionInterface;

/**
 * Audit writer implementation backed by audit.write_event() —
 * a SECURITY DEFINER function in the audit schema (created by migration
 * 2026_05_10_000010_audit__create_events_table).
 *
 * Calling this function from the application connection means the INSERT
 * participates in the caller's transaction. If the calling domain rolls
 * back, the audit row rolls back too — no orphaned audit records.
 */
final readonly class PostgresAuditWriter implements AuditWriterContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function writeEvent(
        ?string $actorId,
        string $companyId,
        string $eventType,
        string $aggregate,
        string $aggregateId,
        array $payload,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $requestId = null,
    ): int {
        $row = $this->db->selectOne(
            'SELECT audit.write_event(?::uuid, ?::uuid, ?, ?, ?::uuid, ?::jsonb, ?::inet, ?, ?) AS id',
            [
                $actorId,
                $companyId,
                $eventType,
                $aggregate,
                $aggregateId,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $ipAddress,
                $userAgent,
                $requestId,
            ],
        );

        return (int) $row->id;
    }

    public function writeSecurityEvent(
        string $eventType,
        ?string $userId,
        ?string $ipAddress = null,
        array $details = [],
    ): int {
        return (int) $this->db->table('audit.security_events')->insertGetId([
            'occurred_at' => now(),
            'user_id'     => $userId,
            'event_type'  => $eventType,
            'ip_address'  => $ipAddress,
            'user_agent'  => request()->userAgent(),
            'details'     => json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }
}
