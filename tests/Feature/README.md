# tests/Feature/

End-to-end HTTP exercises that hit a **real Postgres** through the same Laravel kernel as production. Pest discovers them and runs them under `vendor/bin/pest tests/Feature`.

## Prerequisites

A running `accountingdb_test` Postgres database. The CI workflow boots one in a service container; locally you have two options.

### Option 1 — Docker compose (matches CI)

```bash
cd PhilippineAccountingSystem
docker compose up -d postgres redis
docker compose exec postgres psql -U postgres -c "CREATE DATABASE accountingdb_test OWNER postgres;"
docker compose exec postgres psql -U postgres -d accountingdb_test -f /docker-entrypoint-initdb.d/init.sql
```

Then point `.env.testing` at the test DB:

```env
DB_DATABASE=accountingdb_test
```

### Option 2 — Direct (already-running Postgres)

If you already have Postgres on `localhost:5432`:

```bash
psql -h localhost -U postgres -c "CREATE DATABASE accountingdb_test OWNER postgres;"
psql -h localhost -U postgres -d accountingdb_test -f docker/postgres/init.sql
cp .env.example .env.testing
sed -i 's/DB_DATABASE=.*/DB_DATABASE=accountingdb_test/' .env.testing
```

## Running

```bash
# Full feature suite (each test runs migrate:fresh first via TestCase)
vendor/bin/pest tests/Feature

# Single module
vendor/bin/pest tests/Feature/Modules/Tax

# Single test
vendor/bin/pest tests/Feature/Modules/Tax/OsdElectionLockingTest.php
```

Tests that depend on the DB skip automatically when `config('database.connections.pgsql.database')` is unset — so they don't break CI for a `composer install` smoke check that doesn't provision Postgres.

## What's tested here

| File | Coverage |
|---|---|
| `Modules/Tax/OsdElectionLockingTest.php` | First Q-return records the year's election; subsequent quarters refuse mismatched regimes; idempotent re-confirm. |
| `Modules/Tax/OsdAmendmentTest.php` | Full lock → supersede → refile cycle; same-regime no-op rejection; MFA gate. |
| `Modules/Tax/BulkImportForm2307Test.php` | 3-row CSV import → 201; duplicate re-upload → 422 with per-row report; bad header → 400; unauthenticated → 401. |
| `Modules/Tax/QuarterlyAnnualLoopTest.php` | Q1+Q2+Q3 `tax_paid` auto-sums into the annual ITR's line 22A (no double-count). |

## What's NOT here yet

- Payroll: compute → approve → JV-posting integration test
- Inventory: receipt → MA roll-forward → issue → negative-stock refusal
- Sales: SI issue → JV post → EIS-fake-gateway acknowledge → OR collect → AR cleared

These exist as Unit tests at the domain layer but the multi-module HTTP flow is queued for the next backend-focused batch.

## Why tests skip without DB

[`TestCase`](../TestCase.php) checks `config('database.connections.pgsql.database')` in `beforeEach`. If unset (no `.env.testing`, no `DB_DATABASE`), the test calls `$this->markTestSkipped()`. CI sets it via the workflow's `services.postgres` block; local dev sets it via `.env.testing`.

This makes the Feature suite **safe to commit alongside non-DB changes** — a contributor without a local Postgres still gets a green local test run, and CI catches the real assertions when the test DB is provisioned.
