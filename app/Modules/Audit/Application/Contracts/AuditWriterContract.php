<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Contracts;

/**
 * Public surface of the Audit module. Every other module emits events
 * through this contract — they never touch audit.events directly.
 *
 * Implementations must be transactional with the caller: if the calling
 * domain transaction rolls back, the audit row must roll back too. (We
 * do this by running INSIDE the same DB connection rather than a separate
 * one — see PostgresAuditWriter.)
 */
interface AuditWriterContract
{
    /**
     * @param  string|null  $actorId      uuid of the user who triggered the event (null for system)
     * @param  string       $companyId    uuid of the tenant company
     * @param  string       $eventType    dot-cased: 'journalentry.posted', 'eissubmission.acknowledged'
     * @param  string       $aggregate    PHP class short name: 'JournalEntry'
     * @param  string       $aggregateId  uuid of the affected row
     * @param  array<string, mixed>  $payload  canonical state of the event
     */
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
    ): int;

    /**
     * Security events (logins, MFA, permission denials).
     *
     * @param  array<string, mixed>  $details
     */
    public function writeSecurityEvent(
        string $eventType,
        ?string $userId,
        ?string $ipAddress = null,
        array $details = [],
    ): int;
}
