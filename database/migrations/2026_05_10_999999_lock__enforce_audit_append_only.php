<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Final-pass lockdown — runs LAST (filename starts with 999999).
 *
 * Revokes UPDATE/DELETE on audit.events at the role level for everyone
 * except the function owner (pha_audit). Combined with the BEFORE UPDATE/
 * DELETE triggers, this gives defense in depth: even a misbehaving SECURITY
 * DEFINER function cannot tamper with the chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            -- audit.events: append-only for everyone
            REVOKE UPDATE, DELETE, TRUNCATE ON audit.events FROM PUBLIC;
            REVOKE UPDATE, DELETE, TRUNCATE ON audit.events FROM pha_app;
            GRANT  SELECT ON audit.events TO pha_app, pha_reader;
            -- INSERT happens via SECURITY DEFINER audit.write_event(); never directly.
        SQL);

        DB::unprepared(<<<'SQL'
            -- audit.security_events is append-only too
            REVOKE UPDATE, DELETE, TRUNCATE ON audit.security_events FROM PUBLIC;
            REVOKE UPDATE, DELETE, TRUNCATE ON audit.security_events FROM pha_app;
            GRANT INSERT, SELECT ON audit.security_events TO pha_app;
        SQL);
    }

    public function down(): void
    {
        // Re-grant for rollback (rare in practice; CAS PTU forbids audit DELETE)
        DB::unprepared('GRANT UPDATE, DELETE ON audit.events TO pha_app');
    }
};
