# Database Documentation — Philippine Accounting System

This folder is the canonical reference for the PHA database design. Every schema and table is documented with a Mermaid ERD, indexes, triggers, and cross-schema reference notes.

---

## Files

| File | Purpose |
|---|---|
| [erd.md](erd.md) | **Master ERD** — bird's-eye view, schema map, cross-schema relationships, core invariants |
| [erd-identity.md](erd-identity.md) | Companies, branches, users, roles, permissions, MFA, sessions, consent records |
| [erd-accounting.md](erd-accounting.md) | Chart of Accounts (ltree), journal entries, fiscal periods, FX rates, cost centers, recurring templates |
| [erd-sales.md](erd-sales.md) | Customers, sales invoices, official receipts, POS, document series, sales returns |
| [erd-inventory.md](erd-inventory.md) | Items, variants, barcodes, warehouses, stock movements, lots, costing history |
| [erd-procurement.md](erd-procurement.md) | Vendors, RFQs, POs, GRNs, vendor bills, payment vouchers, 3-way match |
| [erd-payroll.md](erd-payroll.md) | Compensation, payroll runs, payslips, statutory rate tables, loans, 13th month |
| [erd-hr.md](erd-hr.md) | Employees, employment history, departments, positions, attendance, leave |
| [erd-projects.md](erd-projects.md) | Projects, phases, tasks, timesheets, milestones, WIP, progress billings |
| [erd-manufacturing.md](erd-manufacturing.md) | BOMs, routings, work centers, work orders, production runs, costing |
| [erd-tax.md](erd-tax.md) | Tax codes, ATC codes, BIR forms, 2307, 2316, alphalist, EIS submissions |
| [erd-audit.md](erd-audit.md) | Hash-chained event log, backup logs, security events |

---

## How to Read These Docs

**Start with [erd.md](erd.md)** — it gives you the schema map and the master cross-schema ERD. Then drill into the per-schema file you care about.

Each per-schema file follows the same structure:
1. **Purpose** + BIR/PFRS alignment notes
2. **Mermaid ERD** with all key tables and relationships
3. **Tables summary** with row estimates and critical indexes
4. **Triggers & constraints** that enforce BIR/business rules at the DB level
5. **Cross-schema references** — what this schema reads from / writes to others

---

## Rendering

The Mermaid blocks render natively in:
- **GitHub** web UI (no setup)
- **VS Code** with the [Markdown Preview Mermaid Support](https://marketplace.visualstudio.com/items?itemName=bierner.markdown-mermaid) extension
- **PNG/SVG export** via `mmdc` (Mermaid CLI):
  ```bash
  npm install -g @mermaid-js/mermaid-cli
  mmdc -i erd.md -o rendered/erd.png -w 2400 -H 1800
  ```

Rendered images live in `docs/database/rendered/` (generated in CI; not committed manually).

---

## Source of Truth & Drift Detection

The Markdown ERD files are the **design source of truth**. The actual database structure is created by Laravel migrations in `database/migrations/`.

A CI job (`.github/workflows/docs.yml`) runs `schemacrawler` against the dev database after migrations and **diffs** the live schema against the documented ERD. If a migration adds a table or column that isn't in the ERD, the PR fails until docs are updated.

```bash
# Locally, run the same check:
php artisan pha:check-erd-drift
```

---

## Bootstrap

The cluster (database, roles, extensions, schemas) is created by [`docker/postgres/init.sql`](../../docker/postgres/init.sql), which runs automatically on first PostgreSQL container start.

Tables are created by Laravel migrations:

```bash
docker compose up -d postgres redis
docker compose exec app php artisan pha:install
# → schemas verified · 47 migrations applied · 8 seeders run · BIR-compliance verified ✓
```

See the architecture plan §4.7 for the full bootstrap detail.

---

## Conventions

| Convention | Detail |
|---|---|
| **One schema per bounded context** | `identity`, `accounting`, `sales`, etc. |
| **No cross-schema FK constraints** | Cross-schema references are UUID-only; integrity via Application/Contracts |
| **No DELETE on financial rows** | Soft-void with `voided_at`; `REVOKE DELETE` enforces this at the DB |
| **Append-only audit** | `audit.events` triggers block UPDATE/DELETE; INSERT computes hash chain |
| **Period locking** | `accounting.fiscal_periods.locked_at` triggers reject writes for closed periods |
| **Sequential numbering** | `sales.document_series` row-locked allocator; voids preserve numbers (BIR-required) |
| **Money type** | `numeric(18,2)` for PHP, `numeric(18,4)` for foreign currency intermediates |
| **PII encryption** | TIN, SSS, PhilHealth, HDMF, bank# stored encrypted via `pgcrypto` |

---

## Updating the ERDs

When you write a migration that adds, removes, or changes a table:

1. Update the relevant `erd-<schema>.md` file in the same PR
2. Re-run `php artisan pha:check-erd-drift` locally to confirm the docs match
3. CI will fail your PR if the ERD is out of sync

This is **enforced** — the docs and the database stay in lock-step.

---

## Related Reading

- Architecture plan: [act-as-senior-system-wild-spring.md](../../../../.claude/plans/act-as-senior-system-wild-spring.md)
- Module structure: [../architecture/modules.md](../architecture/modules.md) *(coming in Phase 0)*
- BIR compliance posture: [../bir/compliance.md](../bir/compliance.md) *(coming in Phase 0)*
