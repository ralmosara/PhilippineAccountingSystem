<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit events — hash-chained, append-only.
 *
 * Required for BIR CAS Permit-to-Use (RR 9-2009 §6.2). Triggers below ensure
 * the chain can never be tampered with from inside the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE audit.events (
                id           bigserial PRIMARY KEY,
                occurred_at  timestamptz NOT NULL DEFAULT now(),
                actor_id     uuid,
                company_id   uuid NOT NULL,
                event_type   text NOT NULL,
                aggregate    text NOT NULL,
                aggregate_id uuid NOT NULL,
                payload      jsonb NOT NULL,
                prev_hash    bytea,
                current_hash bytea NOT NULL,
                ip_address   inet,
                user_agent   text,
                request_id   text
            );

            CREATE INDEX events_company_time   ON audit.events (company_id, occurred_at);
            CREATE INDEX events_aggregate      ON audit.events (aggregate, aggregate_id);
            CREATE INDEX events_event_type     ON audit.events (event_type, occurred_at);
            CREATE INDEX events_actor          ON audit.events (actor_id, occurred_at);
            CREATE INDEX events_payload_gin    ON audit.events USING gin (payload jsonb_path_ops);

            COMMENT ON TABLE audit.events IS
                'Hash-chained immutable event log. INSERT-only via SECURITY DEFINER function. '
                'Required for BIR CAS RR 9-2009 §6.2.';
        SQL);

        // -----------------------------------------------------------------
        // Hash-chain trigger (BEFORE INSERT)
        // -----------------------------------------------------------------
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit.enforce_chain()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE last_hash bytea;
            BEGIN
                SELECT current_hash INTO last_hash
                  FROM audit.events
                 WHERE company_id = NEW.company_id
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE;

                NEW.prev_hash := last_hash;
                NEW.current_hash := digest(
                    coalesce(last_hash, ''::bytea) || convert_to(NEW.payload::text, 'UTF8'),
                    'sha256'
                );
                RETURN NEW;
            END $$;

            CREATE TRIGGER audit_events_chain
                BEFORE INSERT ON audit.events
                FOR EACH ROW EXECUTE FUNCTION audit.enforce_chain();
        SQL);

        // -----------------------------------------------------------------
        // Append-only enforcement
        // -----------------------------------------------------------------
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit.block_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit.events is append-only (BIR CAS RR 9-2009 §6.2)'
                    USING ERRCODE = 'P0001';
            END $$;

            CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit.events
                FOR EACH ROW EXECUTE FUNCTION audit.block_mutation();
            CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit.events
                FOR EACH ROW EXECUTE FUNCTION audit.block_mutation();
        SQL);

        // -----------------------------------------------------------------
        // SECURITY DEFINER insert function — the only path the app uses
        // -----------------------------------------------------------------
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit.write_event(
                _actor_id     uuid,
                _company_id   uuid,
                _event_type   text,
                _aggregate    text,
                _aggregate_id uuid,
                _payload      jsonb,
                _ip_address   inet  DEFAULT NULL,
                _user_agent   text  DEFAULT NULL,
                _request_id   text  DEFAULT NULL
            )
            RETURNS bigint
            LANGUAGE plpgsql
            SECURITY DEFINER
            AS $$
            DECLARE _id bigint;
            BEGIN
                INSERT INTO audit.events (
                    actor_id, company_id, event_type, aggregate, aggregate_id,
                    payload, current_hash, ip_address, user_agent, request_id
                ) VALUES (
                    _actor_id, _company_id, _event_type, _aggregate, _aggregate_id,
                    _payload, '\x'::bytea, _ip_address, _user_agent, _request_id
                ) RETURNING id INTO _id;
                RETURN _id;
            END $$;

            -- Owned by pha_audit; pha_app may EXECUTE but not modify rows directly
            ALTER FUNCTION audit.write_event(uuid, uuid, text, text, uuid, jsonb, inet, text, text)
                OWNER TO pha_audit;
            GRANT EXECUTE ON FUNCTION audit.write_event(uuid, uuid, text, text, uuid, jsonb, inet, text, text)
                TO pha_app;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS audit.write_event(uuid, uuid, text, text, uuid, jsonb, inet, text, text)');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_chain ON audit.events');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_update ON audit.events');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_delete ON audit.events');
        DB::unprepared('DROP FUNCTION IF EXISTS audit.enforce_chain()');
        DB::unprepared('DROP FUNCTION IF EXISTS audit.block_mutation()');
        DB::unprepared('DROP TABLE IF EXISTS audit.events CASCADE');
    }
};
