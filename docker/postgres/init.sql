-- =============================================================================
-- Philippine Accounting System (PHA) — Database Bootstrap
-- =============================================================================
-- Runs once on first PostgreSQL container start.
-- Mounted at /docker-entrypoint-initdb.d/init.sql by docker-compose.yml.
--
-- Creates: database, roles, extensions, schemas, default privileges.
-- Does NOT create tables — those come from Laravel migrations
-- (see database/migrations/* and `php artisan pha:install`).
--
-- BIR compliance posture: schemas are owned by least-privilege roles;
-- audit schema is INSERT-only for the app role; financial schemas
-- have UPDATE/DELETE locked down by post-migration scripts.
-- =============================================================================

\set ON_ERROR_STOP on

-- -----------------------------------------------------------------------------
-- 1. Database
-- -----------------------------------------------------------------------------
SELECT 'CREATE DATABASE accountingdb
        WITH ENCODING ''UTF8''
             LC_COLLATE ''en_US.UTF-8''
             LC_CTYPE   ''en_US.UTF-8''
             TEMPLATE template0'
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'accountingdb')\gexec

\connect accountingdb

-- -----------------------------------------------------------------------------
-- 2. Roles (passwords come from environment via psql variables / docker secrets)
-- -----------------------------------------------------------------------------
DO $$
BEGIN
    -- Application role (owns schemas, runs migrations, handles all CRUD except audit mutation)
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pha_app') THEN
        EXECUTE format('CREATE ROLE pha_app LOGIN PASSWORD %L',
                       coalesce(current_setting('pha.app_password', true), 'Mypass123'));
    END IF;

    -- Read-only role for reporting / analytics dashboards
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pha_reader') THEN
        EXECUTE format('CREATE ROLE pha_reader LOGIN PASSWORD %L',
                       coalesce(current_setting('pha.reader_password', true), 'change_me_reader'));
    END IF;

    -- INSERT-only role for audit emission (used via SECURITY DEFINER functions)
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pha_audit') THEN
        EXECUTE format('CREATE ROLE pha_audit LOGIN PASSWORD %L',
                       coalesce(current_setting('pha.audit_password', true), 'change_me_audit'));
    END IF;
END $$;

-- -----------------------------------------------------------------------------
-- 3. Extensions
-- -----------------------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS pgcrypto;       -- column-level encryption + digest()
CREATE EXTENSION IF NOT EXISTS citext;         -- case-insensitive text (emails)
CREATE EXTENSION IF NOT EXISTS ltree;          -- hierarchical paths (CoA, departments)
CREATE EXTENSION IF NOT EXISTS pg_trgm;        -- fuzzy search on names
CREATE EXTENSION IF NOT EXISTS unaccent;       -- normalize Filipino diacritics
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";    -- UUID v4 generation

-- -----------------------------------------------------------------------------
-- 4. Schemas (one per bounded context)
-- -----------------------------------------------------------------------------
CREATE SCHEMA IF NOT EXISTS identity      AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS accounting    AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS sales         AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS inventory     AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS procurement   AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS payroll       AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS hr            AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS projects      AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS manufacturing AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS tax           AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS reporting     AUTHORIZATION pha_app;
CREATE SCHEMA IF NOT EXISTS audit         AUTHORIZATION pha_audit;

COMMENT ON SCHEMA identity      IS 'Companies, branches, users, roles, permissions, MFA';
COMMENT ON SCHEMA accounting    IS 'Chart of Accounts, journal entries, fiscal periods, FX rates';
COMMENT ON SCHEMA sales         IS 'Customers, sales invoices, official receipts, POS, document series';
COMMENT ON SCHEMA inventory     IS 'Items, warehouses, stock movements, costing';
COMMENT ON SCHEMA procurement   IS 'Vendors, purchase orders, vendor bills, 3-way match';
COMMENT ON SCHEMA payroll       IS 'Payroll runs, payslips, statutory deductions (SSS/PHIC/HDMF/BIR)';
COMMENT ON SCHEMA hr            IS 'Employees, departments, attendance, leave';
COMMENT ON SCHEMA projects      IS 'Project accounting, timesheets, WIP recognition';
COMMENT ON SCHEMA manufacturing IS 'BOM, work orders, production runs, costing';
COMMENT ON SCHEMA tax           IS 'BIR forms, ATC codes, 2307, 2316, alphalist, EIS submissions';
COMMENT ON SCHEMA reporting     IS 'Materialized views, report runs, financial statements';
COMMENT ON SCHEMA audit         IS 'Hash-chained immutable event log (BIR CAS RR 9-2009 §6.2)';

-- -----------------------------------------------------------------------------
-- 5. Search path — Laravel migrations don't need to qualify every table
-- -----------------------------------------------------------------------------
ALTER ROLE pha_app SET search_path = public, identity, accounting, sales,
    inventory, procurement, payroll, hr, projects, manufacturing, tax, reporting;

ALTER ROLE pha_reader SET search_path = public, identity, accounting, sales,
    inventory, procurement, payroll, hr, projects, manufacturing, tax, reporting, audit;

-- -----------------------------------------------------------------------------
-- 6. Default privileges
-- -----------------------------------------------------------------------------

-- pha_reader: SELECT on everything pha_app creates
GRANT USAGE ON SCHEMA identity, accounting, sales, inventory, procurement,
                       payroll, hr, projects, manufacturing, tax, reporting, audit
    TO pha_reader;

ALTER DEFAULT PRIVILEGES FOR ROLE pha_app IN SCHEMA identity, accounting, sales,
    inventory, procurement, payroll, hr, projects, manufacturing, tax, reporting
    GRANT SELECT ON TABLES TO pha_reader;

ALTER DEFAULT PRIVILEGES FOR ROLE pha_app IN SCHEMA identity, accounting, sales,
    inventory, procurement, payroll, hr, projects, manufacturing, tax, reporting
    GRANT SELECT ON SEQUENCES TO pha_reader;

-- pha_audit: owns audit schema; pha_app can SELECT but not mutate directly
GRANT USAGE ON SCHEMA audit TO pha_app;

ALTER DEFAULT PRIVILEGES FOR ROLE pha_audit IN SCHEMA audit
    GRANT SELECT ON TABLES TO pha_app, pha_reader;

-- audit.events INSERT will be granted to pha_app via SECURITY DEFINER function
-- created by Laravel migration (see audit__create_events_table.php).
-- UPDATE/DELETE/TRUNCATE on audit.events are blocked by triggers + GRANT revocation.

-- -----------------------------------------------------------------------------
-- 7. Sanity comments on database
-- -----------------------------------------------------------------------------
COMMENT ON DATABASE accountingdb IS
    'Philippine Accounting System — single-tenant enterprise, BIR CAS + EIS compliant. '
    'Owned by pha_app; read access via pha_reader; audit emission via pha_audit. '
    'See docs/database/erd.md for the full schema documentation.';

-- -----------------------------------------------------------------------------
-- 8. Verification banner
-- -----------------------------------------------------------------------------
DO $$
DECLARE schema_count int;
BEGIN
    SELECT count(*) INTO schema_count
      FROM information_schema.schemata
     WHERE schema_name IN ('identity','accounting','sales','inventory','procurement',
                           'payroll','hr','projects','manufacturing','tax','reporting','audit');

    IF schema_count <> 12 THEN
        RAISE EXCEPTION 'Expected 12 schemas, found %', schema_count;
    END IF;

    RAISE NOTICE '====================================================================';
    RAISE NOTICE '  PHA database bootstrap complete';
    RAISE NOTICE '  Database:  accountingdb';
    RAISE NOTICE '  Schemas:   12 (identity, accounting, sales, inventory, procurement,';
    RAISE NOTICE '                 payroll, hr, projects, manufacturing, tax, reporting, audit)';
    RAISE NOTICE '  Roles:     pha_app (DDL+DML), pha_reader (SELECT), pha_audit (audit insert)';
    RAISE NOTICE '  Extensions: pgcrypto, citext, ltree, pg_trgm, unaccent, uuid-ossp';
    RAISE NOTICE '';
    RAISE NOTICE '  Next: run `php artisan migrate --force && php artisan db:seed`';
    RAISE NOTICE '        or one-shot: `php artisan pha:install`';
    RAISE NOTICE '====================================================================';
END $$;
